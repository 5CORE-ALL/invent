<?php

namespace App\Services\Amazon;

use App\Models\AmazonUtilizationCount;
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
     * Suggested SBID from U2%/U1% bands (same thresholds as the grid: red below 66%, pink above 99%).
     *
     * - Both red: L1cpc×1.1, else L2cpc×1.1, else L7cpc×1.1, else 0.75 when all CPCs zero.
     * - Both pink: L1cpc×0.90 (uses L1 CPC or a positive costPerClick fallback applied to all tiers).
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
        $fb = ($costPerClickFallback !== null && $costPerClickFallback > 0) ? $costPerClickFallback : null;

        $l1 = $l1Cpc > 0 ? $l1Cpc : 0.0;
        $l2 = $l2Cpc > 0 ? $l2Cpc : 0.0;
        $l7 = $l7Cpc > 0 ? $l7Cpc : 0.0;

        if ($l1 <= 0.0 && $l2 <= 0.0 && $l7 <= 0.0 && $fb !== null) {
            $l1 = $l2 = $l7 = $fb;
        }

        if ($ub2 < 66.0 && $ub1 < 66.0) {
            if ($l1 > 0.0) {
                return ['sbid' => round($l1 * 1.1, 2), 'band' => 'under'];
            }
            if ($l2 > 0.0) {
                return ['sbid' => round($l2 * 1.1, 2), 'band' => 'under'];
            }
            if ($l7 > 0.0) {
                return ['sbid' => round($l7 * 1.1, 2), 'band' => 'under'];
            }

            return ['sbid' => 0.75, 'band' => 'under'];
        }

        if ($ub2 > 99.0 && $ub1 > 99.0) {
            if ($l1 > 0.0) {
                return ['sbid' => round($l1 * 0.9, 2), 'band' => 'over'];
            }

            return ['sbid' => null, 'band' => 'none'];
        }

        return ['sbid' => null, 'band' => 'none'];
    }
}
