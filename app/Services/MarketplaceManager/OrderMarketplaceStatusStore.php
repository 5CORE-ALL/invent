<?php

namespace App\Services\MarketplaceManager;

use App\Services\ShipmentTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saves what each marketplace reports for an order (the "Order Status" column on /order-fulfillment)
 * and turns a marketplace "delivered / in transit" into the Tracking Status when the carrier gave none.
 */
class OrderMarketplaceStatusStore
{
    public const TABLE = 'order_fulfillment_marketplace_statuses';

    public function __construct(protected ChannelOrderDeliveryStatus $delivery)
    {
    }

    public static function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /**
     * Save the marketplace status of every given order row.
     *
     * @param  iterable<array<string, mixed>>  $rows  fulfillment rows: mm_slug, order_id, status, tracking, carrier
     * @return array{saved: int, tracking_applied: int}
     */
    public function snapshot(iterable $rows): array
    {
        $out = ['saved' => 0, 'tracking_applied' => 0];
        if (! self::available()) {
            return $out;
        }

        $now = now();
        $batch = [];
        $seen = [];
        $flush = function () use (&$batch, &$out): void {
            if ($batch === []) {
                return;
            }
            DB::table(self::TABLE)->upsert(
                $batch,
                ['mm_slug', 'order_id'],
                ['order_status', 'tracking_status', 'fetched_at', 'updated_at']
            );
            $out['saved'] += count($batch);
            $batch = [];
        };

        foreach ($rows as $row) {
            $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
            $orderId = trim((string) ($row['order_id'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
            if ($slug === '' || $slug === 'manual' || $orderId === '' || $status === '' || $status === '—') {
                continue;
            }
            $key = $slug.'|'.$orderId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $tracking = $this->delivery->statusFromMarketplaceText($status);
            $batch[] = [
                'mm_slug' => mb_substr($slug, 0, 64),
                'order_id' => mb_substr($orderId, 0, 128),
                'order_status' => mb_substr($status, 0, 191),
                'tracking_status' => $tracking,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($batch) >= 500) {
                $flush();
            }

            $number = trim((string) ($row['tracking'] ?? ''));
            if ($tracking !== null && $number !== '') {
                if ($this->applyTrackingStatus($number, (string) ($row['carrier'] ?? ''), $tracking, 'Marketplace order status: '.$status)) {
                    $out['tracking_applied']++;
                }
            }
        }
        $flush();

        return $out;
    }

    /**
     * @param  list<string>  $orderIds
     * @return array<string, object{order_status: ?string, fetched_at: ?string}> keyed "slug|order_id"
     */
    public function forOrders(array $orderIds): array
    {
        if ($orderIds === [] || ! self::available()) {
            return [];
        }
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_map('strval', $orderIds))), 500) as $chunk) {
            foreach (DB::table(self::TABLE)->whereIn('order_id', $chunk)->get(['mm_slug', 'order_id', 'order_status', 'fetched_at']) as $row) {
                $out[strtolower((string) $row->mm_slug).'|'.trim((string) $row->order_id)] = $row;
            }
        }

        return $out;
    }

    /**
     * Marketplace says delivered / in transit: use it as the Tracking Status, but never replace a
     * better status the carrier already gave.
     */
    public function applyTrackingStatus(string $number, string $carrier, string $status, string $detail): bool
    {
        if ($status === '' || $number === '' || ! Schema::hasTable('carrier_tracking_statuses')) {
            return false;
        }

        $stored = mb_substr(trim($number), 0, 128);
        $existing = DB::table('carrier_tracking_statuses')->where('tracking_number', $stored)->first();
        $current = trim((string) ($existing->shipment_status ?? ''));
        if ($current !== '' && ! $this->isWeaker($current, $status)) {
            return false;
        }

        $now = now();
        DB::table('carrier_tracking_statuses')->updateOrInsert(
            ['tracking_number' => $stored],
            [
                'carrier' => $carrier !== '' ? mb_substr($carrier, 0, 128) : ($existing->carrier ?? null),
                'shipment_status' => $status,
                'shipment_status_detail' => $detail !== '' ? mb_substr($detail, 0, 512) : null,
                'shipment_checked_at' => $now,
                'updated_at' => $now,
                'created_at' => $existing->created_at ?? $now,
            ]
        );

        return true;
    }

    /** True when $current should give way to the marketplace's $new status. */
    protected function isWeaker(string $current, string $new): bool
    {
        $rank = static fn (string $s): int => match ($s) {
            ShipmentTrackingService::STATUS_DELIVERED => 4,
            ShipmentTrackingService::STATUS_OUT_FOR_DELIV => 3,
            ShipmentTrackingService::STATUS_IN_TRANSIT => 2,
            ShipmentTrackingService::STATUS_EXCEPTION,
            ShipmentTrackingService::STATUS_FAILED,
            ShipmentTrackingService::STATUS_EXPIRED => 1,
            default => 0,
        };

        // An exception / failure only gives way to "delivered".
        if ($rank($current) === 1) {
            return $new === ShipmentTrackingService::STATUS_DELIVERED;
        }

        return $rank($new) > $rank($current);
    }
}
