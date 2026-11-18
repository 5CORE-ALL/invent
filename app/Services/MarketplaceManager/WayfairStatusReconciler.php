<?php

namespace App\Services\MarketplaceManager;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps wayfair_daily_data.status honest.
 *
 * Wayfair only lists open / accepted POs, so a PO that shipped or was delivered never gets a new status
 * from the order fetch and stayed "open" / "accepted" forever (and on the Pending page). A PO is moved on when:
 *  - the carrier says delivered           -> delivered
 *  - the carrier says in transit          -> shipped
 *  - a complete Wayfair fetch no longer lists it -> shipped (tracking known) or closed (no tracking)
 *  - optionally, it is older than N days  -> closed
 * Orders Wayfair still lists with no carrier scan stay open / accepted.
 */
class WayfairStatusReconciler
{
    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CLOSED = 'closed';

    /**
     * @param  list<string>|null  $listedPoNumbers  every PO Wayfair listed as open / accepted in a COMPLETE fetch; null = skip the "no longer listed" rule
     * @param  Carbon|null  $windowStart  first PO date the complete fetch covered (rows before it are not judged by the fetch)
     * @param  Carbon|null  $fetchStartedAt  rows saved after this moment are left for the next run
     * @param  int  $closeOlderThanDays  0 = off; otherwise old open / accepted POs with no carrier answer become closed
     * @return array{checked: int, delivered: int, shipped: int, closed: int, samples: array<string, list<string>>}
     */
    public function reconcile(
        ?array $listedPoNumbers = null,
        ?Carbon $windowStart = null,
        ?Carbon $fetchStartedAt = null,
        int $closeOlderThanDays = 0,
        bool $dryRun = false,
    ): array {
        $stats = ['checked' => 0, 'delivered' => 0, 'shipped' => 0, 'closed' => 0, 'samples' => []];
        if (! Schema::hasTable('wayfair_daily_data')) {
            return $stats;
        }

        $listed = null;
        if ($listedPoNumbers !== null) {
            $listed = [];
            foreach ($listedPoNumbers as $po) {
                $key = strtoupper(trim((string) $po));
                if ($key !== '') {
                    $listed[$key] = true;
                }
            }
        }
        // A day of margin: po_date is a local calendar date, the fetch window starts at a UTC midnight.
        $judgeFrom = $windowStart ? $windowStart->copy()->startOfDay()->addDay()->toDateString() : null;
        $oldBefore = $closeOlderThanDays > 0 ? now()->subDays($closeOlderThanDays)->toDateString() : null;

        $hasFulfillment = Schema::hasTable('order_fulfillment_trackings');
        $hasCarrier = Schema::hasTable('carrier_tracking_statuses');

        DB::table('wayfair_daily_data')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('open', 'accepted')")
            ->select(['id', 'po_number', 'po_date', 'updated_at'])
            ->selectRaw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.tracking_number')), 'null') AS payload_tracking")
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$stats, $listed, $judgeFrom, $fetchStartedAt, $oldBefore, $hasFulfillment, $hasCarrier, $dryRun) {
                $tracking = $this->trackingByPo($rows, $hasFulfillment);
                $carrier = $hasCarrier ? $this->carrierStatusByTracking(array_values($tracking)) : [];

                $moves = [];
                foreach ($rows as $row) {
                    $stats['checked']++;
                    $po = strtoupper(trim((string) $row->po_number));
                    $tn = $tracking[$po] ?? '';
                    $carrierStatus = $tn !== '' ? ($carrier[$tn] ?? '') : '';

                    $target = null;
                    if ($carrierStatus === 'delivered') {
                        $target = self::STATUS_DELIVERED;
                    } elseif (in_array($carrierStatus, ['intransit', 'outfordelivery', 'availableforpickup'], true)) {
                        $target = self::STATUS_SHIPPED;
                    } elseif ($listed !== null && $judgeFrom !== null && $po !== '' && ! isset($listed[$po])
                        && (string) $row->po_date >= $judgeFrom
                        && ($fetchStartedAt === null || ! $row->updated_at || Carbon::parse($row->updated_at)->lt($fetchStartedAt))
                    ) {
                        $target = $tn !== '' ? self::STATUS_SHIPPED : self::STATUS_CLOSED;
                    } elseif ($oldBefore !== null && $row->po_date && (string) $row->po_date < $oldBefore) {
                        $target = self::STATUS_CLOSED;
                    }

                    if ($target !== null) {
                        $moves[$target][] = (int) $row->id;
                        $stats[$target]++;
                        if (count($stats['samples'][$target] ?? []) < 5) {
                            $stats['samples'][$target][] = (string) $row->po_number;
                        }
                    }
                }

                if ($dryRun) {
                    return;
                }
                foreach ($moves as $status => $ids) {
                    DB::table('wayfair_daily_data')
                        ->whereIn('id', $ids)
                        ->update(['status' => $status, 'updated_at' => now()]);
                }
            });

        return $stats;
    }

    /**
     * Tracking per PO (upper-case PO => normalised number): saved on the PO row first, then the fulfillment page table.
     *
     * @param  iterable<object>  $rows
     * @return array<string, string>
     */
    protected function trackingByPo(iterable $rows, bool $hasFulfillment): array
    {
        $out = [];
        $pos = [];
        foreach ($rows as $row) {
            $po = strtoupper(trim((string) $row->po_number));
            if ($po === '') {
                continue;
            }
            $pos[trim((string) $row->po_number)] = true;
            $number = $this->normalizeTracking((string) ($row->payload_tracking ?? ''));
            if ($number !== '') {
                $out[$po] = $number;
            }
        }

        if ($hasFulfillment && $pos !== []) {
            $saved = DB::table('order_fulfillment_trackings')
                ->where('mm_slug', 'wayfair')
                ->whereIn('order_id', array_map('strval', array_keys($pos)))
                ->whereNotNull('tracking_number')
                ->where('tracking_number', '!=', '')
                ->orderBy('id')
                ->get(['order_id', 'tracking_number']);
            foreach ($saved as $rec) {
                $po = strtoupper(trim((string) $rec->order_id));
                $number = $this->normalizeTracking((string) $rec->tracking_number);
                if ($po !== '' && $number !== '' && ! isset($out[$po])) {
                    $out[$po] = $number;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $numbers
     * @return array<string, string> normalised tracking number => lower-case carrier status without separators
     */
    protected function carrierStatusByTracking(array $numbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map('strval', $numbers))));
        $out = [];
        foreach (array_chunk($numbers, 500) as $chunk) {
            $hits = DB::table('carrier_tracking_statuses')
                ->whereIn('tracking_number', $chunk)
                ->get(['tracking_number', 'shipment_status']);
            foreach ($hits as $hit) {
                $key = $this->normalizeTracking((string) $hit->tracking_number);
                $status = strtolower(str_replace([' ', '-', '_'], '', trim((string) $hit->shipment_status)));
                if ($key !== '' && $status !== '') {
                    $out[$key] = $status;
                }
            }
        }

        return $out;
    }

    protected function normalizeTracking(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }
}
