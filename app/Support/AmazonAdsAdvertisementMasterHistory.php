<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advertisement Master Amazon trend: rolling L30 from dated daily SP/SB
 * report rows, not the latest L30 summary snapshot (those go stale when
 * the L30 pull lags and make the spend chart dip/spike falsely).
 */
final class AmazonAdsAdvertisementMasterHistory
{
    public const PARENT = 'Amazon';

    public const KW = 'Amazon · KW';

    public const PT = 'Amazon · PT';

    public const HL = 'Amazon · HL';

    /**
     * @return list<string>
     */
    public static function amazonChannels(): array
    {
        return [self::PARENT, self::KW, self::PT, self::HL];
    }

    /**
     * All Marketplace Master labels a snapshot saved on Pacific day D as D−1
     * (metrics are treated as closed through yesterday).
     */
    public static function channelMasterAsOfDate(string $snapshotDate): string
    {
        return Carbon::parse($snapshotDate, 'America/Los_Angeles')->subDay()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $summaryData
     * @param  array<string, array{spend: float}>  $computedParentByDate
     * @return array<string, mixed>
     */
    public static function rewriteChannelMasterAmazonSpend(array $summaryData, array $computedParentByDate, string $asOfDate): array
    {
        if (! isset($computedParentByDate[$asOfDate]['spend'])) {
            return $summaryData;
        }
        $summaryData['total_ad_spend'] = round((float) $computedParentByDate[$asOfDate]['spend'], 2);

        return $summaryData;
    }

    /**
     * Sum daily measures over the inclusive 30-day window ending on each
     * chart day. {@see $active} on a day is that day's ENABLED count, not an L30 sum.
     *
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>  $daily
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>
     */
    public static function rollDailyToL30(array $daily, string $from, string $to): array
    {
        $out = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $acc = ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0];
            $w = $cursor->copy()->subDays(29);
            while ($w->lte($cursor)) {
                $key = $w->toDateString();
                if (isset($daily[$key])) {
                    $acc['spend'] += (float) $daily[$key]['spend'];
                    $acc['clicks'] += (float) $daily[$key]['clicks'];
                    $acc['sold'] += (float) $daily[$key]['sold'];
                    $acc['sales'] += (float) $daily[$key]['sales'];
                }
                $w->addDay();
            }
            $acc['active'] = (float) ($daily[$d]['active'] ?? 0);
            $out[$d] = $acc;
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Replace Amazon (+ KW/PT/HL) series with computed L30 and fix all-channel
     * totals by swapping the old Amazon parent contribution.
     *
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float, missing_ads?: float}>  $byDate
     * @param  array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float, missing_ads?: float}>>  $byChannel
     * @param  array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>>  $computed
     *        channel => date => measures
     * @return array{0: array<string, array<string, float>>, 1: array<string, array<string, array<string, float>>>}
     */
    public static function overlayOnHistory(array $byDate, array $byChannel, array $computed): array
    {
        $parent = $computed[self::PARENT] ?? [];
        foreach ($parent as $d => $meas) {
            $old = $byChannel[self::PARENT][$d] ?? null;
            $merged = [
                'spend' => round((float) $meas['spend'], 2),
                'clicks' => (float) $meas['clicks'],
                'sold' => (float) $meas['sold'],
                'sales' => round((float) $meas['sales'], 2),
                'active' => (float) $meas['active'],
                'missing_ads' => (float) ($byChannel[self::PARENT][$d]['missing_ads'] ?? 0),
            ];
            $byChannel[self::PARENT][$d] = $merged;

            if (isset($byDate[$d])) {
                $byDate[$d]['spend'] = round($byDate[$d]['spend'] - (float) ($old['spend'] ?? 0) + $merged['spend'], 2);
                $byDate[$d]['clicks'] = $byDate[$d]['clicks'] - (float) ($old['clicks'] ?? 0) + $merged['clicks'];
                $byDate[$d]['sold'] = $byDate[$d]['sold'] - (float) ($old['sold'] ?? 0) + $merged['sold'];
                $byDate[$d]['sales'] = round($byDate[$d]['sales'] - (float) ($old['sales'] ?? 0) + $merged['sales'], 2);
                $byDate[$d]['active'] = $byDate[$d]['active'] - (float) ($old['active'] ?? 0) + $merged['active'];
            }
        }

        foreach ([self::KW, self::PT, self::HL] as $ch) {
            foreach ($computed[$ch] ?? [] as $d => $meas) {
                $byChannel[$ch][$d] = [
                    'spend' => round((float) $meas['spend'], 2),
                    'clicks' => (float) $meas['clicks'],
                    'sold' => (float) $meas['sold'],
                    'sales' => round((float) $meas['sales'], 2),
                    'active' => (float) $meas['active'],
                    'missing_ads' => (float) ($byChannel[$ch][$d]['missing_ads'] ?? 0),
                ];
            }
        }

        return [$byDate, $byChannel];
    }

    /**
     * @return array<string, array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>>
     */
    public static function computedL30ByChannel(string $from, string $to): array
    {
        $loadFrom = Carbon::parse($from)->subDays(29)->toDateString();
        $sp = self::dailySlice('amazon_sp_campaign_reports', $loadFrom, $to, true);
        $sb = self::dailySlice('amazon_sb_campaign_reports', $loadFrom, $to, false);

        $parentDaily = self::addDaily($sp['all'] ?? [], $sb['all'] ?? []);
        $kwDaily = $sp['kw'] ?? [];
        $ptDaily = $sp['pt'] ?? [];
        $hlDaily = $sb['all'] ?? [];

        foreach ($parentDaily as $d => $_) {
            $parentDaily[$d]['active'] = (float) ($sp['all'][$d]['active'] ?? 0) + (float) ($sb['all'][$d]['active'] ?? 0);
        }

        return [
            self::PARENT => self::rollDailyToL30($parentDaily, $from, $to),
            self::KW => self::rollDailyToL30($kwDaily, $from, $to),
            self::PT => self::rollDailyToL30($ptDaily, $from, $to),
            self::HL => self::rollDailyToL30($hlDaily, $from, $to),
        ];
    }

    /**
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>  $a
     * @param  array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>  $b
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>
     */
    private static function addDaily(array $a, array $b): array
    {
        $out = $a;
        foreach ($b as $d => $m) {
            if (! isset($out[$d])) {
                $out[$d] = $m;

                continue;
            }
            $out[$d]['spend'] += $m['spend'];
            $out[$d]['clicks'] += $m['clicks'];
            $out[$d]['sold'] += $m['sold'];
            $out[$d]['sales'] += $m['sales'];
        }

        return $out;
    }

    /**
     * @return array{all: array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>, kw: array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>, pt: array<string, array{spend: float, clicks: float, sold: float, sales: float, active: float}>}
     */
    private static function dailySlice(string $table, string $from, string $to, bool $splitKwPt): array
    {
        $empty = ['all' => [], 'kw' => [], 'pt' => []];
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'report_date_range') || ! Schema::hasColumn($table, 'campaign_id') || ! Schema::hasColumn($table, 'id')) {
            return $empty;
        }

        $cols = Schema::getColumnListing($table);
        $spendExpr = in_array('cost', $cols, true) && in_array('spend', $cols, true)
            ? 'COALESCE(t.cost, t.spend, 0)'
            : (in_array('cost', $cols, true) ? 'COALESCE(t.cost, 0)' : 'COALESCE(t.spend, 0)');
        $clicksExpr = in_array('clicks', $cols, true) ? 'COALESCE(t.clicks, 0)' : '0';
        $soldExpr = in_array('purchases1d', $cols, true)
            ? 'COALESCE(t.purchases1d, 0)'
            : (in_array('purchases', $cols, true) ? 'COALESCE(t.purchases, 0)' : '0');
        $salesExpr = in_array('sales1d', $cols, true)
            ? 'COALESCE(t.sales1d, 0)'
            : (in_array('sales', $cols, true) ? 'COALESCE(t.sales, 0)' : '0');
        $hasName = in_array('campaignName', $cols, true);
        $hasStatus = in_array('campaignStatus', $cols, true);

        $latest = DB::table($table)
            ->select('campaign_id', 'report_date_range', DB::raw('MAX(id) as max_id'))
            ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) = 10')
            ->where('report_date_range', '>=', $from)
            ->where('report_date_range', '<=', $to)
            ->groupBy('campaign_id', 'report_date_range');

        $enabledExpr = $hasStatus
            ? "COUNT(DISTINCT CASE WHEN UPPER(TRIM(IFNULL(t.campaignStatus, ''))) = 'ENABLED' THEN t.campaign_id END)"
            : 'COUNT(DISTINCT t.campaign_id)';
        $q = DB::table($table.' as t')
            ->joinSub($latest, 'x', function ($join) {
                $join->on('t.id', '=', 'x.max_id');
            })
            ->groupBy('t.report_date_range')
            ->orderBy('t.report_date_range')
            ->select('t.report_date_range as d')
            ->selectRaw("SUM({$spendExpr}) as spend")
            ->selectRaw("SUM({$clicksExpr}) as clicks")
            ->selectRaw("SUM({$soldExpr}) as sold")
            ->selectRaw("SUM({$salesExpr}) as sales")
            ->selectRaw("{$enabledExpr} as enabled");
        if ($splitKwPt && $hasName) {
            $kwEnabled = $hasStatus
                ? "COUNT(DISTINCT CASE WHEN t.campaignName LIKE '%KW%' AND UPPER(TRIM(IFNULL(t.campaignStatus, ''))) = 'ENABLED' THEN t.campaign_id END)"
                : "COUNT(DISTINCT CASE WHEN t.campaignName LIKE '%KW%' THEN t.campaign_id END)";
            $ptEnabled = $hasStatus
                ? "COUNT(DISTINCT CASE WHEN t.campaignName LIKE '%PT%' AND UPPER(TRIM(IFNULL(t.campaignStatus, ''))) = 'ENABLED' THEN t.campaign_id END)"
                : "COUNT(DISTINCT CASE WHEN t.campaignName LIKE '%PT%' THEN t.campaign_id END)";
            $q->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%KW%' THEN {$spendExpr} ELSE 0 END) as kw_spend")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%KW%' THEN {$clicksExpr} ELSE 0 END) as kw_clicks")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%KW%' THEN {$soldExpr} ELSE 0 END) as kw_sold")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%KW%' THEN {$salesExpr} ELSE 0 END) as kw_sales")
                ->selectRaw("{$kwEnabled} as kw_enabled")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%PT%' THEN {$spendExpr} ELSE 0 END) as pt_spend")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%PT%' THEN {$clicksExpr} ELSE 0 END) as pt_clicks")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%PT%' THEN {$soldExpr} ELSE 0 END) as pt_sold")
                ->selectRaw("SUM(CASE WHEN t.campaignName LIKE '%PT%' THEN {$salesExpr} ELSE 0 END) as pt_sales")
                ->selectRaw("{$ptEnabled} as pt_enabled");
        }

        $rows = $q->get();

        $out = $empty;
        foreach ($rows as $r) {
            $d = (string) $r->d;
            $out['all'][$d] = [
                'spend' => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold' => (float) $r->sold,
                'sales' => (float) $r->sales,
                'active' => (float) ($r->enabled ?? 0),
            ];
            if ($splitKwPt && $hasName) {
                $out['kw'][$d] = [
                    'spend' => (float) ($r->kw_spend ?? 0),
                    'clicks' => (float) ($r->kw_clicks ?? 0),
                    'sold' => (float) ($r->kw_sold ?? 0),
                    'sales' => (float) ($r->kw_sales ?? 0),
                    'active' => (float) ($r->kw_enabled ?? 0),
                ];
                $out['pt'][$d] = [
                    'spend' => (float) ($r->pt_spend ?? 0),
                    'clicks' => (float) ($r->pt_clicks ?? 0),
                    'sold' => (float) ($r->pt_sold ?? 0),
                    'sales' => (float) ($r->pt_sales ?? 0),
                    'active' => (float) ($r->pt_enabled ?? 0),
                ];
            }
        }

        return $out;
    }
}
