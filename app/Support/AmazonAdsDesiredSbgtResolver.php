<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 6-part SBGT (same rules as /amazon-ads/all) for cron/sync rows.
 * BGT ACOS uses lifetime ACOS, the same input as the SBGT column.
 */
final class AmazonAdsDesiredSbgtResolver
{
    /**
     * @param  iterable<int, object|array<string, mixed>>  $campaigns  campaign_id, campaignName, optional acos / acos_L30 / sbgt
     * @param  'sp'|'sb'|null  $channel  when set, BGT ACOS comes from lifetime daily rows in that channel's report table
     * @param  array<string, float|null>|null  $lifetimeAcosByCid  test/override map; null loads from the report table
     * @param  array<string, float|array{rating?: mixed}>|null  $reviewRatingByCid  test/override map; null loads the campaign SKU rating used by the SBGT column
     * @return array<string, int|null>
     */
    public static function sbgtForCampaigns(iterable $campaigns, ?string $channel = null, ?array $lifetimeAcosByCid = null, ?array $reviewRatingByCid = null): array
    {
        $rows = [];
        $names = [];
        foreach ($campaigns as $campaign) {
            $arr = is_array($campaign) ? $campaign : (array) $campaign;
            $cid = trim((string) ($arr['campaign_id'] ?? $arr['campaignId'] ?? ''));
            if ($cid === '') {
                continue;
            }
            $name = trim((string) ($arr['campaignName'] ?? $arr['campaign_name'] ?? ''));
            $rows[$cid] = $arr;
            if ($name !== '') {
                $names[] = $name;
            }
        }
        if ($rows === []) {
            return [];
        }

        $useLifetime = $channel === 'sp' || $channel === 'sb' || $lifetimeAcosByCid !== null;
        if ($useLifetime && $lifetimeAcosByCid === null && ($channel === 'sp' || $channel === 'sb')) {
            try {
                $lifetimeAcosByCid = self::lifetimeAcosByCampaign($channel, array_keys($rows));
            } catch (Throwable $e) {
                Log::warning('amazon-ads SBGT: lifetime ACOS lookup failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $useLifetime = false;
                $lifetimeAcosByCid = null;
            }
        }

        $pageCvr = [];
        $metricsByName = [];
        $ratingsByCid = self::reviewRatingsForCampaigns(array_keys($rows), $reviewRatingByCid);
        try {
            $pageCvr = AmazonAdsCampaignSkuMetrics::parentListingCvrForCampaignNames($names);
            $metricsByName = AmazonAdsCampaignSkuMetrics::mapForCampaignNames($names);
        } catch (Throwable) {
            $pageCvr = [];
            $metricsByName = [];
        }

        $out = [];
        foreach ($rows as $cid => $arr) {
            $name = trim((string) ($arr['campaignName'] ?? $arr['campaign_name'] ?? ''));
            $pc = $pageCvr[$name] ?? AmazonAdsCampaignSkuMetrics::emptyParentListingCvr();
            $mSku = (isset($metricsByName[$name]) && is_array($metricsByName[$name]))
                ? $metricsByName[$name]
                : [];
            $gm = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($mSku);
            $inv = isset($mSku['inv']) && is_numeric($mSku['inv']) ? (float) $mSku['inv'] : null;
            $ovl = isset($mSku['ovl30']) && is_numeric($mSku['ovl30']) ? (float) $mSku['ovl30'] : null;
            $dilVal = AmazonAdsCampaignSkuMetrics::tabulatorDil($inv, $ovl);
            $digits = preg_replace('/\D+/', '', (string) $cid) ?: '';
            $fromAds = $digits !== '' ? ($ratingsByCid[$digits] ?? null) : null;
            $rating = is_array($fromAds) && isset($fromAds['rating']) && is_numeric($fromAds['rating'])
                ? (float) $fromAds['rating']
                : (isset($mSku['rating']) && is_numeric($mSku['rating']) ? (float) $mSku['rating'] : null);

            $bgtViews = AmazonAdsBgtViewsRule::apply(
                isset($pc['sess7']) && is_numeric($pc['sess7']) ? (float) $pc['sess7'] : 0.0
            )['bgt'] ?? null;
            $cvrIn = isset($pc['page_cvr']) && is_numeric($pc['page_cvr']) ? (float) $pc['page_cvr'] : 0.0;
            $bgtCvr = AmazonAdsBgtCvrRule::apply($cvrIn)['bgt'] ?? null;

            if ($useLifetime) {
                $lt = $lifetimeAcosByCid[$cid] ?? null;
                $bgtAcos = is_numeric($lt) ? AmazonAcosSbgtRule::sbgtFromAcosL30((float) $lt) : null;
            } else {
                $acos = $arr['acos_L30'] ?? $arr['acos'] ?? $arr['ACOS'] ?? null;
                if ($acos === null || ! is_numeric($acos)) {
                    $acos = AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow($arr);
                }
                $bgtAcos = is_numeric($acos) ? AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos) : null;
            }

            $bgtPrc = AmazonAdsBgtPrcRule::apply($gm['price'] ?? null)['bgt'] ?? null;
            $bgtReviews = AmazonAdsBgtReviewsRule::apply($rating)['bgt'] ?? null;
            $bgtDil = AmazonAdsBgtDilRule::apply($dilVal)['bgt'] ?? null;

            $sum = AmazonAdsSbgt::sumFromParts($bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtReviews, $bgtDil);
            if ($sum === null && isset($arr['sbgt']) && is_numeric($arr['sbgt'])) {
                $sum = (int) $arr['sbgt'];
            }
            $out[$cid] = $sum;
        }

        return $out;
    }

    /**
     * Same review rating as the SBGT column: lowest campaign-SKU rating, then the metrics rating.
     *
     * @param  list<string>  $campaignIds
     * @param  array<string, float|array{rating?: mixed}>|null  $override
     * @return array<string, array{rating: float|null}>
     */
    private static function reviewRatingsForCampaigns(array $campaignIds, ?array $override): array
    {
        if ($override !== null) {
            $out = [];
            foreach ($override as $id => $rating) {
                $digits = preg_replace('/\D+/', '', (string) $id) ?: '';
                if ($digits === '') {
                    continue;
                }
                $value = is_array($rating) ? ($rating['rating'] ?? null) : $rating;
                $out[$digits] = ['rating' => is_numeric($value) ? (float) $value : null];
            }

            return $out;
        }

        try {
            $found = AmazonAdsCampaignSkuMetrics::minRatingForCampaignIds($campaignIds);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($found as $cid => $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[(string) $cid] = ['rating' => isset($row['rating']) && is_numeric($row['rating']) ? (float) $row['rating'] : null];
        }

        return $out;
    }

    /**
     * Lifetime ACOS % from calendar-day rows. Same rule as the LT ACOS column:
     * spend ÷ sales × 100, or 100 when there is spend and no sales.
     *
     * @param  'sp'|'sb'  $channel
     * @param  list<string>  $campaignIds
     * @return array<string, float|null>
     */
    public static function lifetimeAcosByCampaign(string $channel, array $campaignIds): array
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        $campaignIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $campaignIds
        ), static fn (string $id) => $id !== '')));
        if ($campaignIds === [] || ! Schema::hasTable($table)) {
            return [];
        }
        $columns = Schema::getColumnListing($table);
        $costCol = in_array('cost', $columns, true) ? 'cost' : (in_array('spend', $columns, true) ? 'spend' : null);
        $salesCol = in_array('sales30d', $columns, true) ? 'sales30d' : (in_array('sales', $columns, true) ? 'sales' : null);
        if ($costCol === null || $salesCol === null || ! in_array('campaign_id', $columns, true) || ! in_array('report_date_range', $columns, true)) {
            return [];
        }

        $map = [];
        foreach (array_chunk($campaignIds, 200) as $chunk) {
            $found = DB::table($table)
                ->select(
                    'campaign_id',
                    DB::raw('SUM(`'.$costCol.'`) as life_cost'),
                    DB::raw('SUM(`'.$salesCol.'`) as life_sales')
                )
                ->whereIn('campaign_id', $chunk)
                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                ->groupBy('campaign_id')
                ->get();
            foreach ($found as $row) {
                $cid = trim((string) ($row->campaign_id ?? ''));
                if ($cid === '') {
                    continue;
                }
                $map[$cid] = self::lifetimeAcosPercent((float) ($row->life_cost ?? 0), (float) ($row->life_sales ?? 0));
            }
        }

        return $map;
    }

    public static function lifetimeAcosPercent(float $cost, float $sales): ?float
    {
        if (! is_finite($cost) || ! is_finite($sales)) {
            return null;
        }
        if ($sales > 0) {
            $n = ($cost / $sales) * 100;

            return is_finite($n) ? round($n, 2) : null;
        }
        if ($cost > 0) {
            return 100.0;
        }

        return null;
    }
}
