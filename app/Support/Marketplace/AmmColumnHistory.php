<?php

namespace App\Support\Marketplace;

use App\Models\BadgeDataHistory;
use App\Support\Badges\AllMarketplaceMasterBadgeCalculator;
use Carbon\Carbon;

/**
 * Daily history for the Yesterday / Today columns on /all-marketplace-master
 * (Y Orders, Y Views, Y CVR, T Orders, T Views, T CVR).
 *
 * Rows go into badges_data_histories:
 *   - per channel  -> page_name "amm-channel:{snapshotKey}"
 *   - all channels -> page_name "all-marketplace-master"
 * Yesterday values are stored under the day they were measured, so the graph is a
 * true one-point-per-day series. Today values are stored under the Pacific capture
 * date; the last hourly run of the day is the day's final value.
 */
class AmmColumnHistory
{
    public const PAGE_PREFIX = 'amm-channel:';

    public const ALL_PAGE = 'all-marketplace-master';

    /** Chart metric keys that read from this history. */
    public const FIELDS = [
        'y_orders', 'y_views', 'y_cvr', 'y_ads_pct', 't_orders', 't_views', 't_cvr', 't_ads_pct',
        // INV block: Inv@SP / Inv@LP $ per DIL band (all channels), one point per day.
        'inv_sp_zero', 'inv_sp_red', 'inv_sp_green', 'inv_sp_pink',
        'inv_lp_zero', 'inv_lp_red', 'inv_lp_green', 'inv_lp_pink',
    ];

    /**
     * Channels whose yesterday ad spend comes from a real per-day campaign report
     * (see YesterdayMarketplaceMetricsService: Amazon, eBay 1/2/3, Temu 1/2, Shopify Google Ads).
     * Every other channel reports 0 spend there, which is "no source", not "no ads".
     */
    private const YESTERDAY_ADS_SOURCES = [
        'amazon', 'ebay', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree',
        'temu', 'temu2', 'shopify', 'shopifyb2c',
    ];

    public static function hasYesterdayAdsSource(string $channel): bool
    {
        $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));

        return in_array($key, self::YESTERDAY_ADS_SOURCES, true);
    }

    /**
     * True when the saved y_ad_spend is a trustworthy one-day figure for Y Ads% / T Ads%.
     */
    public static function yesterdayAdsMeasured(string $channel, mixed $ySpend, mixed $yDaySales): bool
    {
        if (! self::hasYesterdayAdsSource($channel) || $ySpend === null || $yDaySales === null) {
            return false;
        }
        $sales = (float) $yDaySales;
        $spend = (float) $ySpend;

        return $sales > 0 && $spend >= 0 && $spend <= $sales;
    }

    /**
     * Measured yesterday Ads% for a channel payload row, or null when the row has no trustworthy one.
     *
     * @param  array<string, mixed>  $row
     */
    public static function measuredAdsRate(array $row): ?float
    {
        if (empty($row['Y Ads Measured'])) {
            return null;
        }
        $sales = self::num($row['Y Day Sales'] ?? 0);
        if ($sales <= 0) {
            return null;
        }

        return (self::num($row['Y Ad Spend'] ?? 0) / $sales) * 100;
    }

    /**
     * Save today's Inv@SP / Inv@LP $ per DIL band under the all-channels page so the INV band
     * dots open the same standard history graph as every other badge.
     *
     * @param  list<array<string, mixed>>  $slices  inventoryDilPiesFromMetrics() slices
     */
    public static function recordInvBands(array $slices, ?Carbon $day = null): void
    {
        $data = [];
        foreach ($slices as $s) {
            $key = (string) ($s['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $data['inv_sp_'.$key] = (float) ($s['inv_at_sp'] ?? 0);
            $data['inv_lp_'.$key] = (float) ($s['inv_at_lp'] ?? 0);
        }
        if ($data !== []) {
            BadgeDataHistory::recordPage(self::ALL_PAGE, $data, $day);
        }
    }

    /**
     * One-time copy of the older cache-only band history (channel_master_inv_dil_hist)
     * into badges_data_histories so existing days are not lost from the graph.
     *
     * @param  array<string, array<string, mixed>>  $hist  date => ['red_sp' => ..., 'red_lp' => ...]
     */
    public static function backfillInvBandsFromCache(array $hist): void
    {
        foreach ($hist as $date => $row) {
            if (! is_array($row) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                continue;
            }
            $data = [];
            foreach (['zero', 'red', 'green', 'pink'] as $band) {
                if (isset($row[$band.'_sp'])) {
                    $data['inv_sp_'.$band] = (float) $row[$band.'_sp'];
                }
                if (isset($row[$band.'_lp'])) {
                    $data['inv_lp_'.$band] = (float) $row[$band.'_lp'];
                }
            }
            if ($data !== []) {
                BadgeDataHistory::recordPage(self::ALL_PAGE, $data, Carbon::parse((string) $date, BadgeDataHistory::TZ));
            }
        }
    }

    /**
     * True when the all-channels Y history has too few distinct days for a trend dot / graph.
     * channel:calculate-data uses this to backfill past days once, like the Sales history.
     */
    public static function yHistoryIsThin(int $minDays = 14): bool
    {
        try {
            $since = now(BadgeDataHistory::TZ)->subDays(30)->toDateString();
            foreach (['y_orders', 'y_views'] as $field) {
                $days = BadgeDataHistory::query()
                    ->where('page_name', self::ALL_PAGE)
                    ->where('field', $field)
                    ->whereDate('snapshot_date', '>=', $since)
                    ->distinct()
                    ->count('snapshot_date');
                if ($days < $minDays) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Re-record Y Orders / Y Views / Y CVR for the last $days Pacific days (ending $end,
     * default yesterday). Shared by channel:calculate-data and the standalone command.
     *
     * @param  null|callable(string): void  $progress
     * @return int values written
     */
    public static function backfillYesterday(int $days = 30, ?Carbon $end = null, ?callable $progress = null): int
    {
        $days = max(1, min(120, $days));
        $end = ($end ?: Carbon::now(BadgeDataHistory::TZ)->subDay())->copy()->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $channels = \App\Models\ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('id')
            ->pluck('channel')
            ->map(fn ($name) => AllMarketplaceMasterBadgeCalculator::snapshotKey((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $svc = app(\App\Services\Support\YesterdayMarketplaceMetricsService::class);
        $total = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $ymd = $day->toDateString();
            $rows = [];
            foreach ($channels as $key) {
                try {
                    $row = $svc->metricsForPacificDate($key, $ymd);
                } catch (\Throwable $e) {
                    report($e);
                    $row = null;
                }
                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            $written = self::recordYesterday($rows, $day->copy());
            $total += $written;
            if ($progress) {
                $progress(sprintf('%s: %d channel row(s), %d value(s) saved', $ymd, count($rows), $written));
            }
        }

        return $total;
    }

    /**
     * Distinct saved days per Y field for the all-channels page (for the command's log line).
     *
     * @return array<string, int>
     */
    public static function yHistoryDayCounts(): array
    {
        $out = [];
        $since = now(BadgeDataHistory::TZ)->subDays(30)->toDateString();
        foreach (['y_orders', 'y_views', 'y_cvr'] as $field) {
            try {
                $out[$field] = (int) BadgeDataHistory::query()
                    ->where('page_name', self::ALL_PAGE)
                    ->where('field', $field)
                    ->whereDate('snapshot_date', '>=', $since)
                    ->distinct()
                    ->count('snapshot_date');
            } catch (\Throwable $e) {
                $out[$field] = -1;
            }
        }

        return $out;
    }

    /**
     * Reverb Y Views used to be saved as raw impressions (÷1000 is what the page shows).
     * Drops those rows (and the All totals / CVR built from them) so they get re-recorded
     * scaled. Self-limiting: only fires while an unscaled Reverb value is still saved.
     */
    public static function purgeUnscaledReverbYHistory(): bool
    {
        try {
            $reverbPage = self::PAGE_PREFIX.'reverb';
            $unscaled = BadgeDataHistory::query()
                ->where('page_name', $reverbPage)
                ->where('field', 'y_views')
                ->where('value', '>', 100000)
                ->exists();
            if (! $unscaled) {
                return false;
            }

            BadgeDataHistory::query()
                ->where('page_name', $reverbPage)
                ->whereIn('field', ['y_views', 'y_cvr'])
                ->delete();
            BadgeDataHistory::query()
                ->where('page_name', self::ALL_PAGE)
                ->whereIn('field', ['y_views', 'y_cvr'])
                ->delete();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Amazon Y Views / Y CVR points that no settled SP-API day backs: older runs saved 0 or
     * copied the rolling amazon_datsheets.sessions_l1 total (the same number for days).
     * Deletes them so the chart only plots real daily values.
     *
     * @return list<string> Pacific dates whose Amazon points were removed (their all-channel
     *                      totals need re-saving)
     */
    public static function purgeUnbackedAmazonYViews(int $days = 45): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('channel_yesterday_views')) {
                return [];
            }
            $since = now(BadgeDataHistory::TZ)->subDays($days)->toDateString();
            $backed = \App\Models\ChannelYesterdayView::query()
                ->where('channel', 'amazon')
                ->where('views', '>', 0)
                ->whereDate('snapshot_date', '>=', $since)
                ->get(['snapshot_date'])
                ->map(fn ($r) => $r->snapshot_date instanceof \DateTimeInterface
                    ? $r->snapshot_date->format('Y-m-d')
                    : (string) $r->snapshot_date)
                ->all();

            $q = BadgeDataHistory::query()
                ->where('page_name', self::PAGE_PREFIX.'amazon')
                ->whereIn('field', ['y_views', 'y_cvr'])
                ->whereDate('snapshot_date', '>=', $since);
            if ($backed !== []) {
                $q->whereNotIn('snapshot_date', $backed);
            }

            $dates = (clone $q)->distinct()->pluck('snapshot_date')
                ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string) $d, 0, 10))
                ->all();
            if ($dates !== []) {
                $q->delete();
            }

            return array_values($dates);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function pageFor(string $channelKey): string
    {
        return $channelKey === 'all' ? self::ALL_PAGE : self::PAGE_PREFIX.$channelKey;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  YesterdayMarketplaceMetricsService::formatRow() rows
     */
    public static function recordYesterday(array $rows, Carbon $day): int
    {
        $written = 0;
        $totOrders = 0.0;
        $totViews = 0.0;
        $totViewQty = 0.0;
        $anyOrders = false;
        $anyViews = false;
        $totAdSpend = 0.0;
        $totAdSales = 0.0;

        foreach ($rows as $row) {
            $key = AllMarketplaceMasterBadgeCalculator::snapshotKey((string) ($row['channel'] ?? ''));
            if ($key === '') {
                continue;
            }

            $data = [];
            if (! empty($row['computed'])) {
                $data['y_orders'] = (float) ($row['orders'] ?? 0);
                $totOrders += $data['y_orders'];
                $anyOrders = true;
            }
            $views = $row['views'] ?? null;
            // Views carried from an older settled day (Amazon) belong to that day's history point.
            if (! empty($row['views_as_of'])) {
                $views = null;
            }
            if ($views !== null && $views !== '') {
                $data['y_views'] = (float) $views;
                $totViews += (float) $views;
                $anyViews = true;
                if ((float) $views > 0) {
                    $totViewQty += (float) ($row['qty'] ?? 0);
                    $data['y_cvr'] = round(((float) ($row['qty'] ?? 0) / (float) $views) * 100, 4);
                }
            }

            // Y Ads% = yesterday ad spend ÷ yesterday sales (only for channels with yesterday sales).
            // Same basis as the page's Y Day Sales; channels without a real daily ad source are skipped.
            $ySales = (float) (($row['pft_sales'] ?? 0) > 0 ? $row['pft_sales'] : ($row['sales'] ?? 0));
            $ySpend = (float) ($row['ad_spend'] ?? 0);
            if (! empty($row['computed']) && self::yesterdayAdsMeasured((string) ($row['channel'] ?? ''), $ySpend, $ySales)) {
                $data['y_ads_pct'] = round(($ySpend / $ySales) * 100, 4);
                $totAdSpend += $ySpend;
                $totAdSales += $ySales;
            }

            if ($data !== []) {
                BadgeDataHistory::recordPage(self::PAGE_PREFIX.$key, $data, $day);
                $written += count($data);
            }
        }

        $all = [];
        if ($totAdSales > 0) {
            $all['y_ads_pct'] = round(($totAdSpend / $totAdSales) * 100, 4);
        }
        if ($anyOrders) {
            $all['y_orders'] = $totOrders;
        }
        if ($anyViews) {
            $all['y_views'] = $totViews;
            if ($totViews > 0) {
                $all['y_cvr'] = round(($totViewQty / $totViews) * 100, 4);
            }
        }
        if ($all !== []) {
            BadgeDataHistory::recordPage(self::ALL_PAGE, $all, $day);
            $written += count($all);
        }

        return $written;
    }

    /**
     * Today columns — same pace formulas as todayPartsFromRow() / tCvrPartsFromRow() on the page.
     *
     * @param  list<array<string, mixed>>  $rows  channel payload rows (getAllMarketplaceMasterChannelPayload()['data'])
     */
    public static function recordToday(array $rows): int
    {
        $written = 0;
        $totViews = 0.0;
        $totUnits = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['Channel '] ?? $row['Channel'] ?? '');
            $key = AllMarketplaceMasterBadgeCalculator::snapshotKey($name);
            if ($key === '') {
                continue;
            }

            $sales = self::num($row['Today Sales'] ?? 0);
            $l30 = self::num($row['L30 Sales'] ?? 0);
            $views = self::num($row['Total Views'] ?? 0);
            $l30Orders = self::num($row['L30 Orders'] ?? 0);
            $ratio = ($sales > 0 && $l30 > 0) ? $sales / $l30 : 0.0;

            $data = [
                't_orders' => ($ratio > 0 && $l30Orders > 0) ? $l30Orders * $ratio : 0.0,
                't_views' => ($ratio > 0 && $views > 0) ? $views * $ratio : 0.0,
            ];

            // Today Ads% = the channel's Ads% applied to today's sales (same as todayPartsFromRow()).
            if ($sales > 0) {
                // Rate = latest measured day's Ads% where the channel has a real daily ad source
                // (ad platforms do not report today live); otherwise the channel's L30 Ads%.
                $adsRaw = self::measuredAdsRate($row) ?? self::num($row['Ads%'] ?? ($row['TACOS'] ?? 0));
                $data['t_ads_pct'] = round((float) $adsRaw, 4);
            }

            if ($ratio > 0 && $views > 0) {
                $tViews = $views * $ratio;
                // Today CVR = T Orders ÷ T Views; fall back to listing CVR when the channel has no orders.
                $tUnits = $l30Orders > 0
                    ? $l30Orders * $ratio
                    : (($cvr = self::listingCvr($key, $row, $views)) !== null ? $tViews * $cvr / 100 : null);
                if ($tUnits !== null) {
                    $data['t_cvr'] = round(($tUnits / $tViews) * 100, 4);
                    $totViews += $tViews;
                    $totUnits += $tUnits;
                }
            }

            BadgeDataHistory::recordPage(self::PAGE_PREFIX.$key, $data);
            $written += count($data);
        }

        if ($totViews > 0) {
            BadgeDataHistory::recordPage(self::ALL_PAGE, ['t_cvr' => round(($totUnits / $totViews) * 100, 4)]);
            $written++;
        }

        return $written;
    }

    /**
     * Mirrors listingCvrFromRow() in all-marketplace-master.blade.php.
     *
     * @param  array<string, mixed>  $row
     */
    private static function listingCvr(string $key, array $row, float $views): ?float
    {
        if ($key === 'amazon' || $key === 'reverb') {
            $units = self::num($row['Qty'] ?? 0) ?: self::num($row['L30 Orders'] ?? 0);

            return ($units / $views) * 100;
        }
        if (array_key_exists('CVR', $row) && $row['CVR'] !== null && $row['CVR'] !== '') {
            return self::num($row['CVR']);
        }

        return (self::num($row['Qty'] ?? 0) / $views) * 100;
    }

    private static function num(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);

        return ($cleaned === '' || $cleaned === '-') ? 0.0 : (float) $cleaned;
    }
}
