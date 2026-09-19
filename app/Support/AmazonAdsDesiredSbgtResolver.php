<?php

namespace App\Support;

use Throwable;

/**
 * 6-part SBGT (same rules as /amazon-ads/all) for cron/sync rows.
 */
final class AmazonAdsDesiredSbgtResolver
{
    /**
     * @param  iterable<int, object|array<string, mixed>>  $campaigns  campaign_id, campaignName, optional acos / acos_L30 / sbgt
     * @return array<string, int|null>
     */
    public static function sbgtForCampaigns(iterable $campaigns): array
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

        $pageCvr = [];
        $metricsByKey = [];
        try {
            $pageCvr = AmazonAdsCampaignSkuMetrics::parentListingCvrForCampaignNames($names);
            $keys = [];
            foreach ($names as $name) {
                $key = AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
            $metricsByKey = AmazonAdsCampaignSkuMetrics::metricsForSkuKeys($keys);
        } catch (Throwable) {
            $pageCvr = [];
            $metricsByKey = [];
        }

        $out = [];
        foreach ($rows as $cid => $arr) {
            $name = trim((string) ($arr['campaignName'] ?? $arr['campaign_name'] ?? ''));
            $pc = $pageCvr[$name] ?? AmazonAdsCampaignSkuMetrics::emptyParentListingCvr();
            $key = AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($name);
            $mSku = ($key !== '' && isset($metricsByKey[$key]) && is_array($metricsByKey[$key]))
                ? $metricsByKey[$key]
                : [];
            $gm = AmazonAdsCampaignSkuMetrics::gridMetricsForPause($mSku);

            $bgtViews = AmazonAdsBgtViewsRule::apply(
                isset($pc['sess7']) && is_numeric($pc['sess7']) ? (float) $pc['sess7'] : 0.0
            )['bgt'] ?? null;
            $bgtCvr = AmazonAdsBgtCvrRule::apply(
                isset($pc['page_cvr']) && is_numeric($pc['page_cvr']) ? (float) $pc['page_cvr'] : null
            )['bgt'] ?? null;

            $acos = $arr['acos_L30'] ?? $arr['acos'] ?? $arr['ACOS'] ?? null;
            if ($acos === null || ! is_numeric($acos)) {
                $acos = AmazonAcosSbgtRule::acosPercentForSbgtFromReportRow($arr);
            }
            $bgtAcos = is_numeric($acos) ? AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos) : null;

            $bgtPrc = AmazonAdsBgtPrcRule::apply($gm['price'] ?? null)['bgt'] ?? null;
            $bgtReviews = AmazonAdsBgtReviewsRule::apply($gm['rating'] ?? null)['bgt'] ?? null;
            $bgtDil = AmazonAdsBgtDilRule::apply($gm['dil'] ?? null)['bgt'] ?? null;

            $sum = AmazonAdsSbgt::sumFromParts($bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtReviews, $bgtDil);
            if ($sum === null && isset($arr['sbgt']) && is_numeric($arr['sbgt'])) {
                $sum = (int) $arr['sbgt'];
            }
            $out[$cid] = $sum;
        }

        return $out;
    }
}
