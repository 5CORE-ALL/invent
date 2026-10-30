<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advertisement Master eBay / eBay 3 trend rebuilt from the dated daily report
 * rows (report_range = Y-m-d) in the priority (KW) and general (PMT) tables.
 * Same math as the live row: sum of the last 31 days through each chart day.
 *
 * eBay 2 is not covered: its report tables only keep the latest L30 snapshot,
 * so there is no per-day data to rebuild from.
 */
final class EbayAdsAdvertisementMasterHistory
{
    /** Window used by the live eBay / eBay 3 rows: now()-31 days through now(). */
    private const WINDOW_BACK_DAYS = 31;

    /** @var array<string, array{priority: string, general: string}> */
    private const TABLES = [
        'eBay' => ['priority' => 'ebay_priority_reports', 'general' => 'ebay_general_reports'],
        'eBay 3' => ['priority' => 'ebay_3_priority_reports', 'general' => 'ebay_3_general_reports'],
    ];

    public static function supports(string $channel): bool
    {
        return isset(self::TABLES[$channel]);
    }

    /**
     * Newest dated report day for the channel (rolling windows are complete up to here).
     */
    public static function latestReportDate(string $channel): ?string
    {
        $latest = null;
        foreach (self::TABLES[$channel] ?? [] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'report_range')) {
                continue;
            }
            $max = DB::table($table)
                ->whereRaw('CHAR_LENGTH(TRIM(report_range)) = 10')
                ->where('report_range', 'NOT LIKE', 'L%')
                ->max('report_range');
            if (is_string($max) && $max !== '' && ($latest === null || $max > $latest)) {
                $latest = $max;
            }
        }

        return $latest;
    }

    /**
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float}>  date => rolling window
     */
    public static function rolling(string $channel, string $from, string $to): array
    {
        $tables = self::TABLES[$channel] ?? null;
        if ($tables === null) {
            return [];
        }

        $loadFrom = Carbon::parse($from)->subDays(self::WINDOW_BACK_DAYS)->toDateString();
        $daily = [];
        self::addDaily($daily, $tables['priority'], $loadFrom, $to, [
            'clicks' => 'cpc_clicks',
            'sales' => 'REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")',
            'sold' => 'cpc_attributed_sales',
            'spend' => 'REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")',
        ]);
        self::addDaily($daily, $tables['general'], $loadFrom, $to, [
            'clicks' => 'clicks',
            'sales' => 'REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")',
            'sold' => 'sales',
            'spend' => 'REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")',
        ]);

        $out = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        while ($cursor->lte($end)) {
            $acc = ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
            $has = false;
            $w = $cursor->copy()->subDays(self::WINDOW_BACK_DAYS);
            while ($w->lte($cursor)) {
                $key = $w->toDateString();
                if (isset($daily[$key])) {
                    $has = true;
                    foreach ($acc as $k => $_) {
                        $acc[$k] += $daily[$key][$k];
                    }
                }
                $w->addDay();
            }
            if ($has) {
                $out[$cursor->toDateString()] = $acc;
            }
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float}>  $daily
     * @param  array<string, string>  $exprs  measure => SQL expression
     */
    private static function addDaily(array &$daily, string $table, string $from, string $to, array $exprs): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'report_range')) {
            return;
        }

        $rows = DB::table($table)
            ->whereRaw('CHAR_LENGTH(TRIM(report_range)) = 10')
            ->where('report_range', '>=', $from)
            ->where('report_range', '<=', $to)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->groupBy('report_range')
            ->select('report_range as d')
            ->selectRaw("COALESCE(SUM({$exprs['clicks']}), 0) as clicks")
            ->selectRaw("COALESCE(SUM({$exprs['sales']}), 0) as sales")
            ->selectRaw("COALESCE(SUM({$exprs['sold']}), 0) as sold")
            ->selectRaw("COALESCE(SUM({$exprs['spend']}), 0) as spend")
            ->get();

        foreach ($rows as $r) {
            $d = (string) $r->d;
            $daily[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0];
            $daily[$d]['spend'] += (float) $r->spend;
            $daily[$d]['clicks'] += (float) $r->clicks;
            $daily[$d]['sold'] += (float) $r->sold;
            $daily[$d]['sales'] += (float) $r->sales;
        }
    }
}
