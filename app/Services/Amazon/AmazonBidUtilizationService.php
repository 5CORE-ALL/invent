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
}
