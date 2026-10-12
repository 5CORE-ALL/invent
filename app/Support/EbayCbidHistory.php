<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One C Bid per listing per Los Angeles day.
 * The chart is the 7 days before today, then today, which is recorded from now on.
 */
final class EbayCbidHistory
{
    public static function today(): string
    {
        return Carbon::now('America/Los_Angeles')->toDateString();
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows
     */
    public static function recordToday(string $account, iterable $rows): void
    {
        if (! Schema::hasTable('ebay_cbid_daily')) {
            return;
        }

        $day = self::today();
        $incoming = [];
        foreach ($rows as $row) {
            $src = is_array($row) ? $row : (array) $row;
            $lid = trim((string) ($src['listing_id'] ?? ''));
            $bid = $src['bid_percentage'] ?? null;
            if ($lid === '' || $bid === null || $bid === '' || ! is_numeric($bid)) {
                continue;
            }
            $incoming[$lid] = round((float) $bid, 2);
        }
        if ($incoming === []) {
            return;
        }

        try {
            $existing = DB::table('ebay_cbid_daily')
                ->where('account', $account)
                ->where('day', $day)
                ->whereIn('listing_id', array_keys($incoming))
                ->pluck('bid_percentage', 'listing_id');
            $now = now();
            $upserts = [];
            foreach ($incoming as $lid => $bid) {
                if ($existing->has($lid) && round((float) $existing[$lid], 2) === $bid) {
                    continue;
                }
                $upserts[] = [
                    'account' => $account,
                    'listing_id' => $lid,
                    'day' => $day,
                    'bid_percentage' => $bid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($upserts, 400) as $chunk) {
                DB::table('ebay_cbid_daily')->upsert(
                    $chunk,
                    ['account', 'listing_id', 'day'],
                    ['bid_percentage', 'updated_at']
                );
            }
            self::seedPriorWeekBulk($account, $incoming);
        } catch (\Throwable $e) {
            Log::warning('C Bid history record failed', ['account' => $account, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Listings with no C Bid in the 7 days before today get those days
     * filled from the current bid, so the chart has a week of points
     * from the first day we start recording.
     *
     * @param  array<string, float>  $incoming
     */
    private static function seedPriorWeekBulk(string $account, array $incoming): void
    {
        if ($incoming === []) {
            return;
        }

        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        $start = $today->copy()->subDays(7)->toDateString();
        $yesterday = $today->copy()->subDay()->toDateString();
        $already = [];
        foreach (array_chunk(array_keys($incoming), 500) as $chunk) {
            $found = DB::table('ebay_cbid_daily')
                ->where('account', $account)
                ->whereBetween('day', [$start, $yesterday])
                ->whereIn('listing_id', $chunk)
                ->distinct()
                ->pluck('listing_id');
            foreach ($found as $lid) {
                $already[(string) $lid] = true;
            }
        }

        $now = now();
        $rows = [];
        foreach ($incoming as $lid => $bid) {
            if (isset($already[(string) $lid])) {
                continue;
            }
            $cursor = $today->copy()->subDays(7);
            $end = $today->copy()->subDay();
            for (; $cursor->lte($end); $cursor->addDay()) {
                $rows[] = [
                    'account' => $account,
                    'listing_id' => (string) $lid,
                    'day' => $cursor->toDateString(),
                    'bid_percentage' => $bid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('ebay_cbid_daily')->upsert(
                $chunk,
                ['account', 'listing_id', 'day'],
                ['bid_percentage', 'updated_at']
            );
        }
    }

    private static function seedPriorWeek(string $account, string $listingId, float $bid): void
    {
        self::seedPriorWeekBulk($account, [$listingId => round($bid, 2)]);
    }

    /**
     * Latest C Bid in the 7 days before today, keyed by listing id.
     *
     * @return array<string, array{bid: float, date: string}>
     */
    public static function priorByListing(string $account): array
    {
        if (! Schema::hasTable('ebay_cbid_daily')) {
            return [];
        }

        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        $start = $today->copy()->subDays(7)->toDateString();
        $end = $today->copy()->subDay()->toDateString();
        $out = [];
        try {
            $rows = DB::table('ebay_cbid_daily')
                ->where('account', $account)
                ->whereBetween('day', [$start, $end])
                ->orderBy('day')
                ->get(['listing_id', 'day', 'bid_percentage']);
            foreach ($rows as $row) {
                if ($row->bid_percentage === null || ! is_numeric($row->bid_percentage)) {
                    continue;
                }
                $out[(string) $row->listing_id] = [
                    'bid' => round((float) $row->bid_percentage, 2),
                    'date' => (string) $row->day,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('C Bid history prior lookup failed', ['account' => $account, 'error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @param  array<string, array{bid: float, date: string}>|null  $prior
     */
    public static function attachPrior(iterable $rows, ?array $prior = null, string $account = 'ebay1'): void
    {
        $prior ??= self::priorByListing($account);
        foreach ($rows as $row) {
            $lid = trim((string) ($row->listing_id ?? ''));
            $hit = ($lid !== '' && isset($prior[$lid])) ? $prior[$lid] : null;
            $row->cbid_prev = $hit['bid'] ?? null;
            $row->cbid_prev_date = $hit['date'] ?? null;
        }
    }

    /**
     * Seven days before today, then today.
     *
     * @return array<int, array{date: string, date_formatted: string, days_ago: int, bid: float|null, is_today: bool}>
     */
    public static function series(string $account, string $listingId): array
    {
        $listingId = trim($listingId);
        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        $start = $today->copy()->subDays(7);
        $stored = [];
        if ($listingId !== '' && Schema::hasTable('ebay_cbid_daily')) {
            try {
                $stored = DB::table('ebay_cbid_daily')
                    ->where('account', $account)
                    ->where('listing_id', $listingId)
                    ->whereBetween('day', [$start->toDateString(), $today->toDateString()])
                    ->pluck('bid_percentage', 'day')
                    ->all();
            } catch (\Throwable $e) {
                Log::warning('C Bid history series failed', ['listing_id' => $listingId, 'error' => $e->getMessage()]);
            }
            $todayRaw = $stored[$today->toDateString()] ?? null;
            if ($todayRaw !== null && is_numeric($todayRaw)) {
                try {
                    self::seedPriorWeek($account, $listingId, (float) $todayRaw);
                    $stored = DB::table('ebay_cbid_daily')
                        ->where('account', $account)
                        ->where('listing_id', $listingId)
                        ->whereBetween('day', [$start->toDateString(), $today->toDateString()])
                        ->pluck('bid_percentage', 'day')
                        ->all();
                } catch (\Throwable $e) {
                    Log::warning('C Bid history prior seed failed', ['listing_id' => $listingId, 'error' => $e->getMessage()]);
                }
            }
        }

        $out = [];
        for ($cursor = $start->copy(); $cursor->lte($today); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $raw = $stored[$key] ?? null;
            $out[] = [
                'date' => $key,
                'date_formatted' => $cursor->format('M d'),
                'days_ago' => (int) $cursor->diffInDays($today),
                'bid' => ($raw !== null && is_numeric($raw)) ? round((float) $raw, 2) : null,
                'is_today' => $cursor->isSameDay($today),
            ];
        }

        return $out;
    }
}
