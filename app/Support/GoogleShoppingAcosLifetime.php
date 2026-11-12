<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lifetime (all-time) ACOS % per Google Ads campaign, used by the BGT Vs ACOS LT rule.
 *
 * Same formula as the grid's ACOS L30 (whole-dollar Spend ÷ whole-dollar GA4 Sales × 100;
 * spend with no sales = 100%), but summed over every daily row of the campaign instead of the L30 window.
 */
final class GoogleShoppingAcosLifetime
{
    private const CACHE_KEY = 'google_shopping_acos_lt_by_campaign_v1';

    /** @var array<string, float>|null */
    private static ?array $memo = null;

    /**
     * @return array<string, float>  campaign_id => lifetime ACOS %
     */
    public static function byCampaignId(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            self::$memo = Cache::remember(self::CACHE_KEY, 600, static fn (): array => self::load());
        } catch (\Throwable) {
            self::$memo = self::load();
        }

        return self::$memo;
    }

    /** Lifetime ACOS % for a campaign, or null when the campaign has no daily rows. */
    public static function forCampaign(mixed $campaignId): ?float
    {
        $key = trim((string) $campaignId);
        if ($key === '') {
            return null;
        }

        return self::byCampaignId()[$key] ?? null;
    }

    public static function forget(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function acosFrom(float $spend, float $sales): float
    {
        $spendR = (int) round($spend);
        $salesR = (int) round($sales);
        if ($salesR >= 1) {
            return ($spendR / $salesR) * 100.0;
        }

        return $spendR > 0 ? 100.0 : 0.0;
    }

    /**
     * @return array<string, float>
     */
    private static function load(): array
    {
        $out = [];
        $rows = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->whereNotNull('date')
            ->selectRaw('campaign_id')
            ->selectRaw('COALESCE(SUM(metrics_cost_micros), 0) / 1000000.0 as spend_lt')
            ->selectRaw('COALESCE(SUM(ga4_actual_revenue), 0) as sales_lt')
            ->groupBy('campaign_id')
            ->get();
        foreach ($rows as $row) {
            $out[(string) $row->campaign_id] = self::acosFrom((float) $row->spend_lt, (float) $row->sales_lt);
        }

        return $out;
    }
}
