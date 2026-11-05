<?php

namespace App\Support\Marketplace;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily snapshots of the Inactive Listings counts (page total + one row per channel).
 * One row per channel per day (America/Los_Angeles), overwritten as the day's counts refresh.
 */
class InactiveListingsHistory
{
    public const TABLE = 'inactive_listings_history';
    public const TZ = 'America/Los_Angeles';

    private static ?bool $tableExists = null;

    private static function ready(): bool
    {
        return self::$tableExists ??= Schema::hasTable(self::TABLE);
    }

    /**
     * Store today's snapshot from the master rows (only call with a complete set of rows).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function record(array $rows): void
    {
        if ($rows === [] || ! self::ready()) {
            return;
        }

        $child = 0;
        $parent = 0;
        foreach ($rows as $row) {
            $child += (int) ($row['cp_inactive_child'] ?? 0);
            $parent += (int) ($row['cp_inactive_parent'] ?? 0);
        }
        if ($child <= 0) {
            return;
        }

        $date = Carbon::now(self::TZ)->toDateString();
        $now = now();
        $payload = [[
            'channel_slug' => 'all',
            'channel_name' => 'All Channels',
            'snapshot_date' => $date,
            'parent_count' => $parent,
            'child_count' => $child,
            'created_at' => $now,
            'updated_at' => $now,
        ]];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['channel_slug'] ?? ''));
            if ($slug === '' || $slug === 'all') {
                continue;
            }
            $payload[] = [
                'channel_slug' => $slug,
                'channel_name' => (string) ($row['channel'] ?? $slug),
                'snapshot_date' => $date,
                'parent_count' => (int) ($row['cp_inactive_parent'] ?? 0),
                'child_count' => (int) ($row['cp_inactive_child'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table(self::TABLE)->upsert($payload, ['channel_slug', 'snapshot_date'], ['channel_name', 'parent_count', 'child_count', 'updated_at']);
    }

    /**
     * @return array{channel: string, metric: string, days: int, data: list<array{date: string, value: int}>}
     */
    public static function series(string $channel, string $metric, int $days): array
    {
        $metric = $metric === 'parent' ? 'parent' : 'child';
        $column = $metric.'_count';
        $data = [];

        if (self::ready()) {
            $query = DB::table(self::TABLE)->where('channel_slug', $channel)->orderBy('snapshot_date');
            if ($days > 0) {
                $query->where('snapshot_date', '>=', Carbon::now(self::TZ)->startOfDay()->subDays($days - 1)->toDateString());
            }
            foreach ($query->get(['snapshot_date', $column]) as $r) {
                $data[] = [
                    'date' => Carbon::parse($r->snapshot_date)->format('M d'),
                    'value' => (int) $r->{$column},
                ];
            }
        }

        return ['channel' => $channel, 'metric' => $metric, 'days' => $days, 'data' => $data];
    }
}
