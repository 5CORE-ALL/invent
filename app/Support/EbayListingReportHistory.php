<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Clicks, ad spend, and sales from ebay_general_reports.
 * The grid shows the last 7 completed days. The chart is one point per report day.
 */
final class EbayListingReportHistory
{
    /**
     * Last 7 days per listing, compared with the 7 days before that.
     *
     * @return array<string, array{clicks: int, clicks_prev: int|null, clicks_prev_date: string|null, spend: float, spend_prev: float|null, spend_prev_date: string|null, sales: int, sales_prev: int|null, sales_prev_date: string|null, range: string}>
     */
    public static function latestByListing(string $table = 'ebay_general_reports'): array
    {
        $table = self::table($table);
        if (! Schema::hasTable($table)) {
            return [];
        }

        $end = Carbon::now('America/Los_Angeles')->startOfDay()->subDay();
        $curStart = $end->copy()->subDays(6);
        $prevEnd = $curStart->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays(6);
        $rangeLabel = $curStart->format('M d').' – '.$end->format('M d');
        $prevLabel = $prevStart->format('M d').' – '.$prevEnd->format('M d');

        try {
            $daily = self::dailyTotals($table, $prevStart->toDateString(), $end->toDateString());
            $l7 = self::rangeTotals($table, 'L7');
        } catch (\Throwable $e) {
            Log::warning('Listing report history lookup failed', ['error' => $e->getMessage()]);

            return [];
        }

        $ids = array_unique(array_merge(array_keys($daily), array_keys($l7)));
        $out = [];
        foreach ($ids as $lid) {
            $days = $daily[$lid] ?? [];
            $cur = self::sumWindow($days, $curStart->toDateString(), $end->toDateString());
            $prev = self::sumWindow($days, $prevStart->toDateString(), $prevEnd->toDateString());
            if ($cur === null && isset($l7[$lid])) {
                $cur = $l7[$lid];
                $prev = null;
            }
            if ($cur === null) {
                continue;
            }
            $out[$lid] = [
                'clicks' => $cur['clicks'],
                'clicks_prev' => $prev['clicks'] ?? null,
                'clicks_prev_date' => $prev !== null ? $prevLabel : null,
                'spend' => $cur['spend'],
                'spend_prev' => $prev['spend'] ?? null,
                'spend_prev_date' => $prev !== null ? $prevLabel : null,
                'sales' => $cur['sales'],
                'sales_prev' => $prev['sales'] ?? null,
                'sales_prev_date' => $prev !== null ? $prevLabel : null,
                'range' => $rangeLabel,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{date: string, date_formatted: string, clicks: int, spend: float, sales: int}>
     */
    public static function series(string $listingId, int $days, string $table = 'ebay_general_reports'): array
    {
        $listingId = trim($listingId);
        $table = self::table($table);
        if ($listingId === '' || ! Schema::hasTable($table)) {
            return [];
        }

        $end = Carbon::now('America/Los_Angeles')->startOfDay()->subDay();
        $start = $days > 0 ? $end->copy()->subDays($days - 1)->toDateString() : null;
        $byDay = [];
        try {
            $query = DB::table($table)
                ->where('listing_id', $listingId)
                ->whereRaw('CHAR_LENGTH(report_range) = 10')
                ->where('report_range', '<=', $end->toDateString());
            if ($start !== null) {
                $query->where('report_range', '>=', $start);
            }
            foreach ($query->get(['report_range', 'clicks', 'ad_fees', 'sales']) as $row) {
                $day = (string) $row->report_range;
                if (! isset($byDay[$day])) {
                    $byDay[$day] = ['clicks' => 0, 'spend' => 0.0, 'sales' => 0];
                }
                $byDay[$day]['clicks'] += (int) ($row->clicks ?? 0);
                $byDay[$day]['spend'] = round($byDay[$day]['spend'] + EbayYesterdaySpend::money($row->ad_fees), 2);
                $byDay[$day]['sales'] += (int) ($row->sales ?? 0);
            }
        } catch (\Throwable $e) {
            Log::warning('Listing report history series failed', ['listing_id' => $listingId, 'error' => $e->getMessage()]);

            return [];
        }

        ksort($byDay);
        $out = [];
        foreach ($byDay as $day => $vals) {
            $out[] = [
                'date' => $day,
                'date_formatted' => Carbon::parse($day)->format('M d'),
                'clicks' => $vals['clicks'],
                'spend' => $vals['spend'],
                'sales' => $vals['sales'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array<string, array{clicks: int, spend: float, sales: int}>>
     */
    /**
     * @param  iterable<int, object>  $rows
     */
    public static function attachToRows(iterable $rows, string $table = 'ebay_general_reports'): void
    {
        $map = self::latestByListing($table);
        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }
            $hit = $map[trim((string) ($row->listing_id ?? ''))] ?? null;
            $row->rpt_clicks = $hit['clicks'] ?? null;
            $row->rpt_clicks_prev = $hit['clicks_prev'] ?? null;
            $row->rpt_clicks_prev_date = $hit['clicks_prev_date'] ?? null;
            $row->rpt_spend = $hit['spend'] ?? null;
            $row->rpt_spend_prev = $hit['spend_prev'] ?? null;
            $row->rpt_spend_prev_date = $hit['spend_prev_date'] ?? null;
            $row->rpt_sales = $hit['sales'] ?? null;
            $row->rpt_sales_prev = $hit['sales_prev'] ?? null;
            $row->rpt_sales_prev_date = $hit['sales_prev_date'] ?? null;
            $row->rpt_range = $hit['range'] ?? null;
        }
    }

    private static function table(string $table): string
    {
        return $table === 'ebay_2_general_reports' ? 'ebay_2_general_reports' : 'ebay_general_reports';
    }

    private static function dailyTotals(string $table, string $start, string $end): array
    {
        $byListing = [];
        $rows = DB::table($table)
            ->whereRaw('CHAR_LENGTH(report_range) = 10')
            ->whereBetween('report_range', [$start, $end])
            ->get(['listing_id', 'report_range', 'clicks', 'ad_fees', 'sales']);
        foreach ($rows as $row) {
            $lid = trim((string) $row->listing_id);
            $day = (string) $row->report_range;
            if ($lid === '' || $day === '') {
                continue;
            }
            if (! isset($byListing[$lid][$day])) {
                $byListing[$lid][$day] = ['clicks' => 0, 'spend' => 0.0, 'sales' => 0];
            }
            $byListing[$lid][$day]['clicks'] += (int) ($row->clicks ?? 0);
            $byListing[$lid][$day]['spend'] = round($byListing[$lid][$day]['spend'] + EbayYesterdaySpend::money($row->ad_fees), 2);
            $byListing[$lid][$day]['sales'] += (int) ($row->sales ?? 0);
        }

        return $byListing;
    }

    /**
     * @return array<string, array{clicks: int, spend: float, sales: int}>
     */
    private static function rangeTotals(string $table, string $range): array
    {
        $out = [];
        $rows = DB::table($table)
            ->where('report_range', $range)
            ->get(['listing_id', 'clicks', 'ad_fees', 'sales']);
        foreach ($rows as $row) {
            $lid = trim((string) $row->listing_id);
            if ($lid === '') {
                continue;
            }
            if (! isset($out[$lid])) {
                $out[$lid] = ['clicks' => 0, 'spend' => 0.0, 'sales' => 0];
            }
            $out[$lid]['clicks'] += (int) ($row->clicks ?? 0);
            $out[$lid]['spend'] = round($out[$lid]['spend'] + EbayYesterdaySpend::money($row->ad_fees), 2);
            $out[$lid]['sales'] += (int) ($row->sales ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, array{clicks: int, spend: float, sales: int}>  $days
     * @return array{clicks: int, spend: float, sales: int}|null
     */
    private static function sumWindow(array $days, string $start, string $end): ?array
    {
        $sum = null;
        foreach ($days as $day => $vals) {
            if ($day < $start || $day > $end) {
                continue;
            }
            if ($sum === null) {
                $sum = ['clicks' => 0, 'spend' => 0.0, 'sales' => 0];
            }
            $sum['clicks'] += $vals['clicks'];
            $sum['spend'] = round($sum['spend'] + $vals['spend'], 2);
            $sum['sales'] += $vals['sales'];
        }

        return $sum;
    }
}
