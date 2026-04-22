<?php

namespace App\Services\Amazon;

use App\Models\AmazonUtilizationCount;
use App\Support\AmazonAdsSbidRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmazonBidUtilizationService
{
    /**
     * @return array{ub7: float, ub1: float}|null
     */
    public static function getUtilization(string $campaignId, string $campaignType): ?array
    {
        $row = AmazonUtilizationCount::query()
            ->where('campaign_id', $campaignId)
            ->where('campaign_type', $campaignType)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'ub7' => (float) ($row->ub7 ?? 0),
            'ub1' => (float) ($row->ub1 ?? 0),
        ];
    }

    /**
     * Prefer utilization table; fall back to computed values.
     *
     * @param  array{ub7: float, ub1: float}|null  $computed
     * @return array{ub7: float, ub1: float, source: string}
     */
    public static function resolveUb(string $campaignId, string $campaignType, ?array $computed): array
    {
        $fromTable = self::getUtilization($campaignId, $campaignType);
        if ($fromTable !== null) {
            return [
                'ub7' => $fromTable['ub7'],
                'ub1' => $fromTable['ub1'],
                'source' => 'amazon_utilization_counts',
            ];
        }

        $ub7 = (float) ($computed['ub7'] ?? 0);
        $ub1 = (float) ($computed['ub1'] ?? 0);

        return [
            'ub7' => $ub7,
            'ub1' => $ub1,
            'source' => 'computed',
        ];
    }

    public static function persistSpSbidM(string $campaignId, float $sbidM): int
    {
        return DB::table('amazon_sp_campaign_reports')
            ->where('campaign_id', $campaignId)
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->update(['sbid_m' => round($sbidM, 2), 'updated_at' => now()]);
    }

    public static function persistSbSbidM(string $campaignId, float $sbidM): int
    {
        return DB::table('amazon_sb_campaign_reports')
            ->where('campaign_id', $campaignId)
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->update(['sbid_m' => round($sbidM, 2), 'updated_at' => now()]);
    }

    public static function logBidDecision(
        string $campaignId,
        string $campaignType,
        float $ub1,
        float $currentBid,
        float $newBid,
        string $source
    ): void {
        Log::info('HL/KW Bid Update', [
            'campaign_id' => $campaignId,
            'campaign_type' => $campaignType,
            'ub1' => round($ub1, 2),
            'current_bid' => round($currentBid, 4),
            'new_bid' => round($newBid, 4),
            'action' => abs($newBid - $currentBid) > 0.0001 ? 'UPDATED' : 'NO_CHANGE',
            'ub_source' => $source,
        ]);
    }

    /**
     * U2% aligned with Amazon Ads All grid: spend₂ / (budget × 2) × 100 (here spend₂ = L2-day spend).
     */
    public static function ub2PercentFromL2Spend(float $budget, float $l2Spend): float
    {
        if ($budget <= 0) {
            return 0.0;
        }

        return ($l2Spend / ($budget * 2.0)) * 100.0;
    }

    /**
     * Suggested SBID from U2%/U1% bands using {@see AmazonAdsSbidRule::resolvedRule()} (thresholds and multipliers).
     *
     * - Both below util_low: L1×m1, else L2×m2, else L7×m7, else both_low_fallback when all CPCs zero.
     * - Both above util_high: L1×both_high_mult_l1 (or null when L1 CPC missing, same as legacy).
     * - Otherwise: sbid null (display "--" in Amazon Ads All).
     *
     * @return array{sbid: float|null, band: 'under'|'over'|'none'}
     */
    public static function sbidFromUb2Ub1Cpc(
        float $ub2,
        float $ub1,
        float $l1Cpc,
        float $l2Cpc,
        float $l7Cpc,
        ?float $costPerClickFallback = null
    ): array {
        $r = AmazonAdsSbidRule::resolvedRule();
        $low = (float) $r['util_low'];
        $high = (float) $r['util_high'];
        $m1 = (float) $r['both_low_mult_l1'];
        $m2 = (float) $r['both_low_mult_l2'];
        $m7 = (float) $r['both_low_mult_l7'];
        $fallback = (float) $r['both_low_fallback'];
        $highM1 = (float) $r['both_high_mult_l1'];

        $fb = ($costPerClickFallback !== null && $costPerClickFallback > 0) ? $costPerClickFallback : null;

        $l1 = $l1Cpc > 0 ? $l1Cpc : 0.0;
        $l2 = $l2Cpc > 0 ? $l2Cpc : 0.0;
        $l7 = $l7Cpc > 0 ? $l7Cpc : 0.0;

        if ($l1 <= 0.0 && $l2 <= 0.0 && $l7 <= 0.0 && $fb !== null) {
            $l1 = $l2 = $l7 = $fb;
        }

        if ($ub2 < $low && $ub1 < $low) {
            if ($l1 > 0.0) {
                return ['sbid' => round($l1 * $m1, 2), 'band' => 'under'];
            }
            if ($l2 > 0.0) {
                return ['sbid' => round($l2 * $m2, 2), 'band' => 'under'];
            }
            if ($l7 > 0.0) {
                return ['sbid' => round($l7 * $m7, 2), 'band' => 'under'];
            }

            return ['sbid' => round($fallback, 2), 'band' => 'under'];
        }

        if ($ub2 > $high && $ub1 > $high) {
            if ($l1 > 0.0) {
                return ['sbid' => round($l1 * $highM1, 2), 'band' => 'over'];
            }

            return ['sbid' => null, 'band' => 'none'];
        }

        return ['sbid' => null, 'band' => 'none'];
    }
}
