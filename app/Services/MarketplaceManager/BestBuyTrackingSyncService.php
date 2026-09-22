<?php

namespace App\Services\MarketplaceManager;

use App\Models\BestBuyOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\BestBuyApiService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Copy Veeqo labels onto Shopify, then ship each unused tracking to Best Buy.
 * One Mirakl shipment per package — never treat the first label as the whole line.
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
        $lineId = trim((string) ($order->order_line_id ?? ''));
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
            $linked = $this->siblingLines($order)->first(
                static fn (BestBuyOrderMetric $row) => trim((string) ($row->shopify_order_id ?? '')) !== ''
            );
            if ($linked !== null) {
                $order = $linked;
                $shopifyOrderId = trim((string) $order->shopify_order_id);
            }
        }
        if ($shopifyOrderId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        if ($tryVeeqoCopy) {
            foreach ($this->siblingLines($order) as $row) {
                try {
                    app(VeeqoShopifyFulfillmentService::class)->fulfillMarketplaceOrder('bestbuy', (int) $row->id);
                } catch (\Throwable $e) {
                    Log::debug('BestBuyTrackingSyncService: Veeqo copy skipped', [
                        'id' => $row->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $order->refresh();
        }

        $last = [
            'success' => false,
            'skipped' => true,
            'message' => 'No unused Shopify tracking for this Best Buy order yet.',
        ];
        $shippedAny = false;
        foreach ($this->siblingLines($order) as $line) {
            $guard = 0;
            while ($guard++ < 12) {
                $line->refresh();
                $result = $this->shipNextUnusedTracking($line);
                $last = $result;
                if (! empty($result['success']) && empty($result['skipped'])) {
                    $shippedAny = true;
                    continue;
                }
                break;
            }
        }

        if ($shippedAny) {
            return [
                'success' => true,
                'action' => 'shipped',
                'message' => (string) ($last['message'] ?? 'Pushed Best Buy tracking.'),
                'shopify_tracking' => $last['shopify_tracking'] ?? null,
                'shopify_carrier' => $last['shopify_carrier'] ?? null,
                'ship_carrier' => $last['ship_carrier'] ?? null,
            ];
        }

        return $last;
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

        $limit = max(1, min(250, $limit));
        $rows = BestBuyOrderMetric::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->whereNotNull('order_line_id')
            ->where('order_line_id', '!=', '')
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere(function ($inner) {
                        $inner->where('status', 'not like', '%cancel%')
                            ->where('status', 'not like', '%refus%');
                    });
            })
            ->where(function ($query) {
                $query->whereNull('order_created_at')
                    ->orWhere('order_created_at', '>=', now()->subDays(180));
            })
            ->orderByDesc('id')
            ->limit(max($limit * 4, 200))
            ->get();

        $attempted = 0;
        $pushed = 0;
        $skipped = 0;
        $seen = [];
        $processed = 0;
        foreach ($rows as $row) {
            $familyKey = trim((string) ($row->order_id ?: $row->channel_order_id ?: $row->id));
            if ($familyKey !== '' && isset($seen[$familyKey])) {
                continue;
            }
            if ($familyKey !== '') {
                $seen[$familyKey] = true;
            }
            if ($this->familyFullyShipped($row)) {
                $skipped++;
                continue;
            }
            $processed++;
            if ($processed > $limit) {
                break;
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

    public static function normalizeTracking(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', $raw) ?? $raw);
    }

    /**
     * @return list<string>
     */
    public static function trackingListFromPayload(mixed $raw): array
    {
        $payload = is_array($raw) ? $raw : [];
        $out = [];
        $push = static function (string $tn) use (&$out): void {
            $tn = self::normalizeTracking($tn);
            if ($tn !== '' && ! in_array($tn, $out, true)) {
                $out[] = $tn;
            }
        };
        foreach ((array) ($payload['shopify_trackings_pushed'] ?? []) as $tn) {
            $push((string) $tn);
        }
        $push((string) ($payload['shopify_tracking_pushed'] ?? ''));

        return $out;
    }

    public static function shippedQtyFromPayload(mixed $raw, int $trackingCount = 0): int
    {
        $payload = is_array($raw) ? $raw : [];
        if (array_key_exists('shopify_shipped_qty', $payload)) {
            return max(0, (int) $payload['shopify_shipped_qty']);
        }

        return max(0, $trackingCount);
    }

    public static function remainingShipQty(int $lineQty, mixed $raw): int
    {
        $lineQty = max(1, $lineQty);
        $pushed = self::trackingListFromPayload($raw);
        $shipped = self::shippedQtyFromPayload($raw, count($pushed));

        return max(0, $lineQty - $shipped);
    }

    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null, shopify_tracking?: string|null, shopify_carrier?: string|null, ship_carrier?: string|null}
     */
    protected function shipNextUnusedTracking(BestBuyOrderMetric $line): array
    {
        $shopifyOrderId = trim((string) ($line->shopify_order_id ?? ''));
        if ($shopifyOrderId === '') {
            $linked = $this->siblingLines($line)->first(
                static fn (BestBuyOrderMetric $row) => trim((string) ($row->shopify_order_id ?? '')) !== ''
            );
            $shopifyOrderId = $linked !== null ? trim((string) $linked->shopify_order_id) : '';
        }
        $sku = trim((string) ($line->sku ?? ''));
        $marketplaceOrderId = trim((string) ($line->channel_order_id ?: $line->order_id));

        $shopify = $this->shopifyTrackingForLine($line, $shopifyOrderId, $sku, $marketplaceOrderId);
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
        $target = $this->lineForUnusedTracking($line, $tracking);
        if ($target === null) {
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

        $remaining = self::remainingShipQty((int) ($target->quantity ?? 1), $target->raw_payload);
        $shipQty = min($remaining, max(1, (int) ($shopify['quantity'] ?? 1)));
        if ($shipQty < 1) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_synced',
                'message' => 'Best Buy line is already fully shipped.',
                'shopify_tracking' => $tracking,
                'shopify_carrier' => $carrier,
                'ship_carrier' => $carrier,
            ];
        }

        $ship = $this->bestBuyApi->createOrderShipment(
            trim((string) ($target->order_id ?? '')),
            trim((string) ($target->order_line_id ?? '')),
            $tracking,
            $carrier,
            $shipQty,
            trim((string) ($target->sku ?? ''))
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

        $this->markTrackingPushed($target, $tracking, $carrier, $shipQty);

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
     * @return array{tracking: ?string, carrier: ?string, quantity: int, error: ?string}
     */
    protected function shopifyTrackingForLine(
        BestBuyOrderMetric $line,
        string $shopifyOrderId,
        string $sku,
        string $marketplaceOrderId
    ): array {
        $exclude = [];
        foreach ($this->siblingLines($line) as $sibling) {
            foreach (self::trackingListFromPayload($sibling->raw_payload) as $tn) {
                $exclude[] = $tn;
            }
        }

        $matched = app(ShopifyFulfillmentTrackingMatcher::class)->match(
            $this->shopifyConfig(),
            $shopifyOrderId,
            $marketplaceOrderId,
            $sku,
            array_values(array_filter([
                $line->order_id,
                $line->channel_order_id,
                $line->order_line_id,
            ])),
            'BestBuyTrackingSyncService',
            $exclude
        );

        return [
            'tracking' => $matched['tracking'] ?? null,
            'carrier' => $matched['carrier'] ?? null,
            'quantity' => max(1, (int) ($matched['quantity'] ?? 1)),
            'error' => $matched['error'] ?? null,
        ];
    }

    protected function alreadyPushedLocally(BestBuyOrderMetric $line, string $shopifyTracking = ''): bool
    {
        $pushed = self::trackingListFromPayload($line->raw_payload);
        if ($shopifyTracking !== '') {
            return in_array(self::normalizeTracking($shopifyTracking), $pushed, true);
        }

        return self::remainingShipQty((int) ($line->quantity ?? 1), $line->raw_payload) < 1;
    }

    protected function markTrackingPushed(
        BestBuyOrderMetric $line,
        string $tracking,
        string $carrier = '',
        int $shipQty = 1
    ): void {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        $list = self::trackingListFromPayload($raw);
        $already = self::shippedQtyFromPayload($raw, count($list));
        $tracking = self::normalizeTracking($tracking);
        if ($tracking !== '' && ! in_array($tracking, $list, true)) {
            $list[] = $tracking;
        }
        $raw['shopify_trackings_pushed'] = $list;
        $raw['shopify_tracking_pushed'] = $tracking;
        $raw['tracking_number'] = $tracking;
        $raw['shopify_shipped_qty'] = $already + max(1, $shipQty);
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
     * @return Collection<int, BestBuyOrderMetric>
     */
    protected function siblingLines(BestBuyOrderMetric $line): Collection
    {
        $orderId = trim((string) ($line->order_id ?? ''));
        $channelId = trim((string) ($line->channel_order_id ?? ''));
        $query = BestBuyOrderMetric::query();
        if ($orderId !== '') {
            $query->where('order_id', $orderId);
        } elseif ($channelId !== '') {
            $query->where('channel_order_id', $channelId);
        } else {
            return collect([$line]);
        }

        $rows = $query->orderBy('id')->get();

        return $rows->isEmpty() ? collect([$line]) : $rows;
    }

    protected function familyFullyShipped(BestBuyOrderMetric $line): bool
    {
        foreach ($this->siblingLines($line) as $row) {
            if (! $this->alreadyPushedLocally($row)) {
                return false;
            }
        }

        return true;
    }

    protected function lineForUnusedTracking(BestBuyOrderMetric $line, string $tracking): ?BestBuyOrderMetric
    {
        $tracking = self::normalizeTracking($tracking);
        if ($tracking === '') {
            return null;
        }
        foreach ($this->siblingLines($line) as $row) {
            if ($this->alreadyPushedLocally($row, $tracking)) {
                continue;
            }
            if (self::remainingShipQty((int) ($row->quantity ?? 1), $row->raw_payload) < 1) {
                continue;
            }

            return $row;
        }

        return null;
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
