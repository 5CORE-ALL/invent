<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Daily clicks, ad spend, and sales from ebay_general_reports.report_range.
 */
final class EbayListingReportHistory
{
    /**
     * Latest dated day and the day before it, per listing.
     *
     * @return array<string, array{clicks: int, clicks_prev: int|null, clicks_prev_date: string|null, spend: float, spend_prev: float|null, spend_prev_date: string|null, sales: int, sales_prev: int|null, sales_prev_date: string|null}>
     */
    public static function latestByListing(): array
    {
        if (! Schema::hasTable('ebay_general_reports')) {
            return [];
        }

        $end = Carbon::now('America/Los_Angeles')->startOfDay()->subDay();
        $start = $end->copy()->subDays(20)->toDateString();
        $byListing = [];
        try {
            $rows = DB::table('ebay_general_reports')
                ->whereRaw('CHAR_LENGTH(report_range) = 10')
                ->whereBetween('report_range', [$start, $end->toDateString()])
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
        } catch (\Throwable $e) {
            Log::warning('Listing report history lookup failed', ['error' => $e->getMessage()]);

            return [];
        }

        $out = [];
        foreach ($byListing as $lid => $days) {
            ksort($days);
            $dates = array_keys($days);
            $lastDay = (string) array_pop($dates);
            $prevDay = $dates === [] ? null : (string) array_pop($dates);
            $last = $days[$lastDay];
            $prev = $prevDay !== null ? $days[$prevDay] : null;
            $out[$lid] = [
                'clicks' => $last['clicks'],
                'clicks_prev' => $prev['clicks'] ?? null,
                'clicks_prev_date' => $prevDay,
                'spend' => $last['spend'],
                'spend_prev' => $prev['spend'] ?? null,
                'spend_prev_date' => $prevDay,
                'sales' => $last['sales'],
                'sales_prev' => $prev['sales'] ?? null,
                'sales_prev_date' => $prevDay,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{date: string, date_formatted: string, clicks: int, spend: float, sales: int}>
     */
    public static function series(string $listingId, int $days): array
    {
        $listingId = trim($listingId);
        if ($listingId === '' || ! Schema::hasTable('ebay_general_reports')) {
            return [];
        }

        $end = Carbon::now('America/Los_Angeles')->startOfDay()->subDay();
        $start = $days > 0 ? $end->copy()->subDays($days - 1)->toDateString() : null;
        $byDay = [];
        try {
            $query = DB::table('ebay_general_reports')
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
}
