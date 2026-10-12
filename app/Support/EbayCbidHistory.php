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

        self::purgeBackfill($account);
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
        } catch (\Throwable $e) {
            Log::warning('C Bid history record failed', ['account' => $account, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Drop days that were copied from today's bid. A real row is inserted on that calendar day.
     */
    private static function purgeBackfill(string $account): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            DB::table('ebay_cbid_daily')
                ->where('account', $account)
                ->whereRaw('`day` < DATE(`created_at`)')
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('C Bid history backfill purge failed', ['account' => $account, 'error' => $e->getMessage()]);
        }
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
                ->whereRaw('`day` >= DATE(`created_at`)')
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
     * Seven days before today from ebay_general_reports.
     * report_range is the day. ad_fees is that day's promoted spend.
     *
     * @return array<int, array{date: string, date_formatted: string, days_ago: int, bid: float|null, impressions: int|null, clicks: int|null, is_today: bool}>
     */
    public static function series(string $account, string $listingId): array
    {
        $listingId = trim($listingId);
        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        $start = $today->copy()->subDays(7);
        $end = $today->copy()->subDay();
        $byDay = [];
        if ($listingId !== '' && Schema::hasTable('ebay_general_reports')) {
            try {
                $rows = DB::table('ebay_general_reports')
                    ->where('listing_id', $listingId)
                    ->whereRaw('CHAR_LENGTH(report_range) = 10')
                    ->whereBetween('report_range', [$start->toDateString(), $end->toDateString()])
                    ->get(['report_range', 'ad_fees', 'impressions', 'clicks']);
                foreach ($rows as $row) {
                    $day = (string) $row->report_range;
                    if (! isset($byDay[$day])) {
                        $byDay[$day] = ['fees' => 0.0, 'impressions' => 0, 'clicks' => 0];
                    }
                    $byDay[$day]['fees'] = round($byDay[$day]['fees'] + self::money($row->ad_fees), 2);
                    $byDay[$day]['impressions'] += (int) ($row->impressions ?? 0);
                    $byDay[$day]['clicks'] += (int) ($row->clicks ?? 0);
                }
            } catch (\Throwable $e) {
                Log::warning('C Bid history general report failed', ['listing_id' => $listingId, 'error' => $e->getMessage()]);
            }
        }

        $out = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $hit = $byDay[$key] ?? null;
            $out[] = [
                'date' => $key,
                'date_formatted' => $cursor->format('M d'),
                'days_ago' => (int) $cursor->diffInDays($today),
                'bid' => $hit ? $hit['fees'] : null,
                'impressions' => $hit['impressions'] ?? null,
                'clicks' => $hit['clicks'] ?? null,
                'is_today' => false,
            ];
        }

        return $out;
    }

    private static function money(mixed $raw): float
    {
        if (is_numeric($raw)) {
            return round((float) $raw, 2);
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $raw);
        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }
}
