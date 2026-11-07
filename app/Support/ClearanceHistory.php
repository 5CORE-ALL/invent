<?php

namespace App\Support;

use App\Models\EbaySkuDailyData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Daily history of the Clearance count (SKUs with Inv Days clearance = YES and INV > 0) and their total INV.
 *
 * snapshotToday() is run hourly by the scheduler (and whenever the history is read and today has no row yet), so a day is
 * saved even when nobody opens a page. backfill() rebuilds earlier days from the clearance change log
 * (inv_days_clearance_logs) and the daily eBay INV snapshots; it never overwrites a live row.
 */
final class ClearanceHistory
{
    public const TZ = 'America/Los_Angeles';

    public static function today(): string
    {
        return Carbon::now(self::TZ)->toDateString();
    }

    private static function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return strtoupper($sku);
    }

    /**
     * Live numbers right now.
     *
     * @return array{count:int, inv:int}
     */
    public static function liveTotals(): array
    {
        if (! Schema::hasTable('inv_days_clearances') || ! Schema::hasTable('shopify_skus')) {
            return ['count' => 0, 'inv' => 0];
        }
        $yes = [];
        foreach (DB::table('inv_days_clearances')->where('value', 'YES')->whereNotNull('sku')->pluck('sku') as $sku) {
            $key = self::skuKey((string) $sku);
            if ($key !== '' && stripos($key, 'PARENT') !== 0) {
                $yes[$key] = true;
            }
        }
        if ($yes === []) {
            return ['count' => 0, 'inv' => 0];
        }
        $count = 0;
        $inv = 0.0;
        $seen = [];
        foreach (DB::table('shopify_skus')->where('inv', '>', 0)->select(['sku', 'inv'])->cursor() as $row) {
            $key = self::skuKey((string) $row->sku);
            if (isset($yes[$key]) && ! isset($seen[$key])) {
                $seen[$key] = true;
                $count++;
                $inv += (float) $row->inv;
            }
        }

        return ['count' => $count, 'inv' => (int) round($inv)];
    }

    /** Save (or refresh) today's row. */
    public static function snapshotToday(): ?array
    {
        if (! Schema::hasTable('clearance_daily_counts')) {
            return null;
        }
        try {
            $t = self::liveTotals();
            DB::table('clearance_daily_counts')->updateOrInsert(
                ['record_date' => self::today()],
                ['sku_count' => $t['count'], 'inv_units' => $t['inv'], 'source' => 'live', 'updated_at' => now(), 'created_at' => now()]
            );

            return $t;
        } catch (Throwable $e) {
            Log::warning('[ClearanceHistory] snapshot failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Rebuild the days that have no row yet (and are not today) from the change log. Returns the number of days written.
     */
    public static function backfill(int $days = 30): int
    {
        if (! Schema::hasTable('clearance_daily_counts') || ! Schema::hasTable('inv_days_clearances')) {
            return 0;
        }
        $days = max(1, min(180, $days));
        $today = Carbon::now(self::TZ)->startOfDay();
        $start = $today->copy()->subDays($days);

        $have = DB::table('clearance_daily_counts')
            ->whereBetween('record_date', [$start->toDateString(), $today->toDateString()])
            ->pluck('record_date')
            ->map(static fn ($d) => Carbon::parse($d)->toDateString())
            ->flip()
            ->all();

        // Every SKU that is, or ever was, on clearance: its creation time and its change log (UTC timestamps).
        $rows = DB::table('inv_days_clearances')->select(['sku_key', 'sku', 'value', 'created_at'])->get();
        $logs = [];
        if (Schema::hasTable('inv_days_clearance_logs')) {
            foreach (DB::table('inv_days_clearance_logs')->orderBy('id')->get(['sku_key', 'sku', 'from_value', 'to_value', 'created_at']) as $l) {
                $logs[(string) $l->sku_key][] = $l;
            }
        }
        $skus = [];
        foreach ($rows as $r) {
            $skus[(string) $r->sku_key] = ['sku' => (string) $r->sku, 'current' => (string) $r->value, 'created' => $r->created_at];
        }
        foreach ($logs as $k => $list) {
            $skus[$k] ??= ['sku' => (string) $list[0]->sku, 'current' => 'NO', 'created' => null];
        }

        // Current INV, used when a day has no INV snapshot for the SKU.
        $currentInv = [];
        if (Schema::hasTable('shopify_skus')) {
            foreach (DB::table('shopify_skus')->select(['sku', 'inv'])->cursor() as $r) {
                $currentInv[self::skuKey((string) $r->sku)] = (float) $r->inv;
            }
        }

        $written = 0;
        for ($d = $start->copy(); $d->lt($today); $d->addDay()) {
            $date = $d->toDateString();
            if (isset($have[$date])) {
                continue;
            }
            $endOfDay = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 23:59:59', self::TZ)->utc();
            $yes = [];
            foreach ($skus as $key => $info) {
                if (stripos($info['sku'], 'PARENT') === 0) {
                    continue;
                }
                $state = null;
                $list = $logs[$key] ?? [];
                foreach ($list as $l) {
                    if (Carbon::parse($l->created_at, 'UTC')->lte($endOfDay)) {
                        $state = (string) $l->to_value;
                    }
                }
                if ($state === null) {
                    // No change yet by that day: the state before the first change (or the current one when it never changed).
                    $created = $info['created'] ? Carbon::parse($info['created'], 'UTC') : null;
                    if ($created !== null && $created->gt($endOfDay)) {
                        $state = 'NO';
                    } else {
                        $state = $list !== [] ? (string) $list[0]->from_value : $info['current'];
                    }
                }
                if ($state === 'YES') {
                    $yes[self::skuKey($info['sku'])] = true;
                }
            }
            if ($yes === []) {
                DB::table('clearance_daily_counts')->insert([
                    'record_date' => $date, 'sku_count' => 0, 'inv_units' => 0, 'source' => 'backfill',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $written++;
                continue;
            }
            $invOnDay = [];
            if (Schema::hasTable('ebay_sku_daily_data')) {
                EbaySkuDailyData::query()->where('record_date', $date)->select(['sku', 'daily_data'])
                    ->chunk(2000, function ($chunk) use (&$invOnDay) {
                        foreach ($chunk as $rec) {
                            $data = is_array($rec->daily_data) ? $rec->daily_data : (json_decode((string) $rec->daily_data, true) ?: []);
                            if (array_key_exists('inv', $data)) {
                                $invOnDay[self::skuKey((string) $rec->sku)] = (float) $data['inv'];
                            }
                        }
                    });
            }
            $count = 0;
            $inv = 0.0;
            foreach (array_keys($yes) as $key) {
                $units = $invOnDay[$key] ?? ($currentInv[$key] ?? 0.0);
                if ($units > 0) {
                    $count++;
                    $inv += $units;
                }
            }
            DB::table('clearance_daily_counts')->insert([
                'record_date' => $date, 'sku_count' => $count, 'inv_units' => (int) round($inv), 'source' => 'backfill',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $written++;
        }

        return $written;
    }

    /**
     * Rows for the graph: one entry per day that has data.
     *
     * @return list<array{date:string,label:string,count:int,inv:int}>
     */
    public static function history(int $days = 30): array
    {
        if (! Schema::hasTable('clearance_daily_counts')) {
            return [];
        }
        $days = max(7, min(90, $days));
        $today = Carbon::now(self::TZ)->startOfDay();
        $start = $today->copy()->subDays($days - 1);

        $hasToday = DB::table('clearance_daily_counts')->where('record_date', $today->toDateString())->exists();
        if (! $hasToday) {
            self::snapshotToday();
        }
        self::backfill($days);

        return DB::table('clearance_daily_counts')
            ->whereBetween('record_date', [$start->toDateString(), $today->toDateString()])
            ->orderBy('record_date')
            ->get(['record_date', 'sku_count', 'inv_units'])
            ->map(static function ($r) {
                $date = Carbon::parse($r->record_date)->toDateString();

                return ['date' => $date, 'label' => substr($date, 5), 'count' => (int) $r->sku_count, 'inv' => (int) $r->inv_units];
            })
            ->values()
            ->all();
    }
}
