<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Channel totals from tiktok_campaign_reports — same badges as /tiktok-1-ads-raw-data.
 */
class Tiktok1AdsRawDataTotals
{
    /**
     * @return array{
     *   count: int,
     *   cost_l30: float,
     *   cost_l1: float,
     *   cost_l7: float,
     *   orders_l30: int,
     *   orders_l1: int,
     *   revenue_l30: float,
     *   revenue_l1: float,
     *   clicks_l30: int,
     *   clicks_l1: int
     * }
     */
    public static function sums(): array
    {
        $empty = [
            'count' => 0,
            'cost_l30' => 0.0,
            'cost_l1' => 0.0,
            'cost_l7' => 0.0,
            'orders_l30' => 0,
            'orders_l1' => 0,
            'revenue_l30' => 0.0,
            'revenue_l1' => 0.0,
            'clicks_l30' => 0,
            'clicks_l1' => 0,
        ];
        if (! Schema::hasTable('tiktok_campaign_reports')) {
            return $empty;
        }

        $rows = DB::table('tiktok_campaign_reports')
            ->selectRaw('UPPER(TRIM(COALESCE(report_range, ""))) as r, COUNT(*) as n, COALESCE(SUM(cost),0) as cost, COALESCE(SUM(gross_revenue),0) as rev, COALESCE(SUM(sku_orders),0) as orders, COALESCE(SUM(product_ad_clicks),0) as clicks')
            ->groupBy('r')
            ->get();

        $out = $empty;
        foreach ($rows as $row) {
            $range = (string) ($row->r ?? '');
            $out['count'] += (int) ($row->n ?? 0);
            if ($range === 'L1') {
                $out['cost_l1'] += (float) ($row->cost ?? 0);
                $out['orders_l1'] += (int) ($row->orders ?? 0);
                $out['revenue_l1'] += (float) ($row->rev ?? 0);
                $out['clicks_l1'] += (int) ($row->clicks ?? 0);
            } elseif ($range === 'L7') {
                $out['cost_l7'] += (float) ($row->cost ?? 0);
            } else {
                $out['cost_l30'] += (float) ($row->cost ?? 0);
                $out['orders_l30'] += (int) ($row->orders ?? 0);
                $out['revenue_l30'] += (float) ($row->rev ?? 0);
                $out['clicks_l30'] += (int) ($row->clicks ?? 0);
            }
        }

        foreach (['cost_l30', 'cost_l1', 'cost_l7', 'revenue_l30', 'revenue_l1'] as $key) {
            $out[$key] = round((float) $out[$key], 2);
        }

        return $out;
    }

    public static function l30Spend(): float
    {
        return (float) (self::sums()['cost_l30'] ?? 0);
    }

    /**
     * L1 Cost badge, or L7 when no L1 spend (same short-window fallback as /tiktok-pricing).
     */
    public static function l1Spend(): float
    {
        $sums = self::sums();
        $l1 = (float) ($sums['cost_l1'] ?? 0);
        if ($l1 > 0) {
            return $l1;
        }

        return (float) ($sums['cost_l7'] ?? 0);
    }

    /**
     * @return array{
     *   clicks: int,
     *   ad_sales: float,
     *   ad_sold: int,
     *   KW Clicks: int,
     *   KW Sales: float,
     *   KW Sold: int,
     *   KW Spent: float,
     *   Total Ad Spend: float,
     *   KW ACOS: float,
     *   KW CVR: float
     * }
     */
    public static function l30AdMetrics(): array
    {
        $sums = self::sums();
        $spend = (float) ($sums['cost_l30'] ?? 0);
        $clicks = (int) ($sums['clicks_l30'] ?? 0);
        $sales = (float) ($sums['revenue_l30'] ?? 0);
        $sold = (int) ($sums['orders_l30'] ?? 0);

        return [
            'clicks' => $clicks,
            'ad_sales' => $sales,
            'ad_sold' => $sold,
            'KW Clicks' => $clicks,
            'KW Sales' => $sales,
            'KW Sold' => $sold,
            'KW Spent' => $spend,
            'Total Ad Spend' => $spend,
            'KW ACOS' => $sales > 0 ? round(($spend / $sales) * 100, 1) : 0,
            'KW CVR' => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0,
        ];
    }
}
