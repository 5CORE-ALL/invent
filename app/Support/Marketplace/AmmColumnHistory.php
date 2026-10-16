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
    public const FIELDS = ['y_orders', 'y_views', 'y_cvr', 't_orders', 't_views', 't_cvr'];

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
            if ($views !== null && $views !== '') {
                $data['y_views'] = (float) $views;
                $totViews += (float) $views;
                $anyViews = true;
                if ((float) $views > 0) {
                    $totViewQty += (float) ($row['qty'] ?? 0);
                    $data['y_cvr'] = round(((float) ($row['qty'] ?? 0) / (float) $views) * 100, 4);
                }
            }

            if ($data !== []) {
                BadgeDataHistory::recordPage(self::PAGE_PREFIX.$key, $data, $day);
                $written += count($data);
            }
        }

        $all = [];
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

            if ($ratio > 0 && $views > 0) {
                $cvr = self::listingCvr($key, $row, $views);
                if ($cvr !== null) {
                    $data['t_cvr'] = round($cvr, 4);
                    $totViews += $views * $ratio;
                    $totUnits += $views * $ratio * $cvr / 100;
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
