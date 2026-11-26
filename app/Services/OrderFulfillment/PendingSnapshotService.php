<?php

namespace App\Services\OrderFulfillment;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Models\OrderFulfillmentPendingSnapshot as Snapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Records and reads the pending-order history behind the badge on /order-fulfillment/pending.
 *
 *  - every hour: one 'hourly' row with the pending orders per site;
 *  - once a day, at 3:30 PM Ohio (Eastern) time: one 'daily' row with what was pending at that moment.
 *    It is only written by the run that starts in the 3:30 PM window, never by a later catch-up,
 *    so every daily record really is the 3:30 PM number and only changes the next day at 3:30 PM.
 */
class PendingSnapshotService
{
    /** Ohio time of the daily record. */
    public const DAILY_HOUR = 15;

    public const DAILY_MINUTE = 30;

    /** A run that starts within this many minutes after 3:30 PM Ohio still counts as the 3:30 PM record. */
    public const DAILY_WINDOW_MINUTES = 10;

    public static function available(): bool
    {
        return Schema::hasTable('order_fulfillment_pending_snapshots');
    }

    /**
     * Count pending orders per site now and save the rows.
     *
     * $daily is true only for the 3:30 PM Ohio scheduler run. The daily record is written when that run
     * started inside the 3:30 PM window; started any other time (a manual run, a late run) it is skipped,
     * so the record is never a later number passed off as the 3:30 PM one.
     *
     * @return array{total: int, sites: int, hourly: bool, daily: bool, daily_date: string, daily_skipped: bool}
     */
    public function capture(bool $daily = false): array
    {
        $tz = Snapshot::TIMEZONE;
        // Stamp the start: counting takes a while, but the numbers describe this moment.
        $now = now();
        $ohio = $now->copy()->setTimezone($tz);
        $date = $ohio->toDateString();

        $counts = app(OrderFulfillmentController::class)->pendingCountsBySite();
        $total = (int) array_sum(array_column($counts, 'orders'));

        Snapshot::create([
            'kind' => Snapshot::KIND_HOURLY,
            'snapshot_date' => $date,
            'captured_at' => $now,
            'total_orders' => $total,
            'counts' => $counts,
        ]);

        $dailyDue = $daily && $this->inDailyWindow($ohio);
        $dailyWritten = false;
        if ($dailyDue && ! Snapshot::query()->where('kind', Snapshot::KIND_DAILY)->whereDate('snapshot_date', $date)->exists()) {
            Snapshot::create([
                'kind' => Snapshot::KIND_DAILY,
                'snapshot_date' => $date,
                'captured_at' => $now,
                'total_orders' => $total,
                'counts' => $counts,
            ]);
            $dailyWritten = true;
        }

        // Hourly rows older than 45 days are not shown anywhere; the daily records are kept.
        Snapshot::query()
            ->where('kind', Snapshot::KIND_HOURLY)
            ->where('captured_at', '<', $now->copy()->subDays(45))
            ->delete();

        return [
            'total' => $total,
            'sites' => count($counts),
            'hourly' => true,
            'daily' => $dailyWritten,
            'daily_date' => $date,
            'daily_skipped' => $daily && ! $dailyDue,
        ];
    }

    /** True from 3:30 PM Ohio until DAILY_WINDOW_MINUTES later. */
    public function inDailyWindow(Carbon $ohio): bool
    {
        $start = $ohio->copy()->setTime(self::DAILY_HOUR, self::DAILY_MINUTE, 0);

        return $ohio->greaterThanOrEqualTo($start)
            && $ohio->lessThan($start->copy()->addMinutes(self::DAILY_WINDOW_MINUTES));
    }

    /**
     * Payload for the badge + graph modal.
     *
     * @return array<string, mixed>
     */
    public function history(string $range = '7d'): array
    {
        $tz = Snapshot::TIMEZONE;
        if (! self::available()) {
            return $this->emptyHistory($range, 'Run "php artisan migrate" first (table order_fulfillment_pending_snapshots is missing).');
        }

        $days = match ($range) {
            '24h' => 1,
            '30d' => 30,
            default => 7,
        };
        $range = match ($range) {
            '24h', '30d' => $range,
            default => '7d',
        };

        $latest = Snapshot::query()->where('kind', Snapshot::KIND_HOURLY)->orderByDesc('captured_at')->first();
        if ($latest === null) {
            return $this->emptyHistory($range, 'No snapshot yet. The first one is taken within the hour.');
        }

        $since = now()->subDays($days);
        $hourly = Snapshot::query()
            ->where('kind', Snapshot::KIND_HOURLY)
            ->where('captured_at', '>=', $since)
            ->orderBy('captured_at')
            ->get();

        $dailyRows = Snapshot::query()
            ->where('kind', Snapshot::KIND_DAILY)
            ->where('snapshot_date', '>=', now($tz)->subDays(90)->toDateString())
            ->orderBy('snapshot_date')
            ->get();

        $previous = Snapshot::query()
            ->where('kind', Snapshot::KIND_HOURLY)
            ->where('captured_at', '<', $latest->captured_at)
            ->orderByDesc('captured_at')
            ->first();

        $ohioLabel = static fn (Carbon $t): string => $t->copy()->setTimezone($tz)->format('M j, g:i A');

        return [
            'range' => $range,
            'timezone' => $tz,
            'daily_time' => '3:30 PM',
            'latest' => [
                'captured_at' => $latest->captured_at->toIso8601String(),
                'label' => $ohioLabel($latest->captured_at),
                'total' => (int) $latest->total_orders,
                'sites' => $this->sitesList($latest->counts ?? [], $previous?->counts),
                'previous_total' => $previous ? (int) $previous->total_orders : null,
            ],
            'hourly' => $hourly->map(fn (Snapshot $s): array => [
                't' => $s->captured_at->toIso8601String(),
                'label' => $ohioLabel($s->captured_at),
                'total' => (int) $s->total_orders,
                'counts' => $this->flatCounts($s->counts ?? []),
            ])->values()->all(),
            'daily' => $dailyRows->map(fn (Snapshot $s): array => [
                'date' => $s->snapshot_date->toDateString(),
                'label' => $s->snapshot_date->format('M j'),
                'recorded_at' => $ohioLabel($s->captured_at),
                'total' => (int) $s->total_orders,
                'counts' => $this->flatCounts($s->counts ?? []),
            ])->values()->all(),
            'labels' => $this->labelMap($hourly, $dailyRows, $latest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyHistory(string $range, string $message): array
    {
        return [
            'range' => $range,
            'timezone' => Snapshot::TIMEZONE,
            'daily_time' => '3:30 PM',
            'latest' => null,
            'hourly' => [],
            'daily' => [],
            'labels' => [],
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, array{label?: string, orders?: int}>  $counts
     * @return array<string, int>
     */
    private function flatCounts(array $counts): array
    {
        $out = [];
        foreach ($counts as $slug => $row) {
            $out[$slug] = (int) ($row['orders'] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, array{label?: string, orders?: int}>  $counts
     * @param  array<string, array{label?: string, orders?: int}>|null  $previous
     * @return list<array{slug: string, label: string, orders: int, delta: ?int}>
     */
    private function sitesList(array $counts, ?array $previous): array
    {
        $out = [];
        foreach ($counts as $slug => $row) {
            $orders = (int) ($row['orders'] ?? 0);
            $out[] = [
                'slug' => (string) $slug,
                'label' => (string) ($row['label'] ?? $slug),
                'orders' => $orders,
                'delta' => $previous === null ? null : $orders - (int) ($previous[$slug]['orders'] ?? 0),
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['orders'] <=> $a['orders']);

        return $out;
    }

    /**
     * @param  iterable<Snapshot>  $hourly
     * @param  iterable<Snapshot>  $daily
     * @return array<string, string>
     */
    private function labelMap(iterable $hourly, iterable $daily, Snapshot $latest): array
    {
        $labels = [];
        foreach ([$hourly, $daily, [$latest]] as $set) {
            foreach ($set as $s) {
                foreach (($s->counts ?? []) as $slug => $row) {
                    if (! empty($row['label'])) {
                        $labels[(string) $slug] = (string) $row['label'];
                    }
                }
            }
        }

        return $labels;
    }
}
