<?php

namespace App\Services\Amazon;

use App\Models\AmazonUtilizationCount;
use App\Support\AmazonAdsSbidRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Same lifetime CPC as the Amazon Ads All SBID cell: total daily cost ÷ total daily clicks.
     */
    public static function lifetimeAvgCpcFromDaily(string $table, string $campaignId, ?string $adType = null): float
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '' || ! Schema::hasTable($table)) {
            return 0.0;
        }
        $costCol = Schema::hasColumn($table, 'cost') ? 'cost' : (Schema::hasColumn($table, 'spend') ? 'spend' : null);
        if ($costCol === null || ! Schema::hasColumn($table, 'clicks')) {
            return 0.0;
        }
        $q = DB::table($table)
            ->where('campaign_id', $campaignId)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        if ($adType !== null && $adType !== '' && Schema::hasColumn($table, 'ad_type')) {
            $q->where('ad_type', $adType);
        }
        $row = $q->selectRaw('SUM(`'.$costCol.'`) as life_cost, SUM(`clicks`) as life_clicks')->first();
        $clicks = (float) ($row->life_clicks ?? 0);
        $cost = (float) ($row->life_cost ?? 0);
        if ($clicks <= 0 || $cost <= 0) {
            return 0.0;
        }
        $n = $cost / $clicks;

        return is_finite($n) && $n > 0 ? $n : 0.0;
    }

    /**
     * CPC1 / CPC2 / CPC3 used by the Amazon Ads grid on a daily row: costPerClick on the
     * campaign's latest calendar day, the day before, and two days before. A missing day
     * or a zero CPC is 0 so SBID can fall through to Avg CPC + 0.10. This is not the L7
     * summary CPC.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function gridDailyCpcTriple(string $table, string $campaignId, ?string $adType = null): array
    {
        $campaignId = trim($campaignId);
        $empty = [0.0, 0.0, 0.0];
        if ($campaignId === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'costPerClick') || ! Schema::hasColumn($table, 'report_date_range')) {
            return $empty;
        }
        $anchorQuery = DB::table($table)
            ->where('campaign_id', $campaignId)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10');
        if ($adType !== null && $adType !== '' && Schema::hasColumn($table, 'ad_type')) {
            $anchorQuery->where('ad_type', $adType);
        }
        $anchor = $anchorQuery->max('report_date_range');
        if (! is_string($anchor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
            return $empty;
        }
        $days = [
            $anchor,
            date('Y-m-d', strtotime($anchor.' -1 day')),
            date('Y-m-d', strtotime($anchor.' -2 day')),
        ];
        $rowsQuery = DB::table($table)
            ->where('campaign_id', $campaignId)
            ->whereIn('report_date_range', $days);
        if ($adType !== null && $adType !== '' && Schema::hasColumn($table, 'ad_type')) {
            $rowsQuery->where('ad_type', $adType);
        }
        $byDay = [];
        foreach ($rowsQuery->get(['report_date_range', 'costPerClick']) as $row) {
            $n = (float) ($row->costPerClick ?? 0);
            $byDay[(string) $row->report_date_range] = is_finite($n) && $n > 0 ? $n : 0.0;
        }

        return [
            (float) ($byDay[$days[0]] ?? 0.0),
            (float) ($byDay[$days[1]] ?? 0.0),
            (float) ($byDay[$days[2]] ?? 0.0),
        ];
    }

    public static function suggestedSbidStorageValue(mixed $computed): ?string
    {
        if (! is_numeric($computed)) {
            return null;
        }
        $n = round((float) $computed, 2);
        if (! is_finite($n) || $n <= 0) {
            return null;
        }

        return number_format($n, 2, '.', '');
    }

    public static function storedSbidMatches(mixed $stored, ?string $want): bool
    {
        $have = self::suggestedSbidStorageValue($stored);
        if ($have === null && is_numeric($stored) && (float) $stored == 0.0) {
            $have = null;
        }

        return $have === $want;
    }

    /**
     * Write the SBID cell suggestion onto the visible report rows and the latest
     * daily row the morning push reads. Does not touch last_sbid (the live Amazon bid).
     *
     * @param  array<int|string, float|string|null>  $sbidByRowId
     * @param  array<string, float|string|null>  $sbidByCampaignId
     */
    public static function persistSuggestedSbidByRowId(string $table, array $sbidByRowId, array $sbidByCampaignId = []): void
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return;
        }
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sbid')) {
            return;
        }
        if ($sbidByCampaignId !== []) {
            foreach (self::latestDailyRowIds($table, array_keys($sbidByCampaignId)) as $campaignId => $latest) {
                $want = self::suggestedSbidStorageValue($sbidByCampaignId[$campaignId] ?? null);
                if (! self::storedSbidMatches($latest['sbid'] ?? null, $want)) {
                    $sbidByRowId[$latest['id']] = $want;
                }
            }
        }
        if ($sbidByRowId === []) {
            return;
        }
        $normalized = [];
        foreach ($sbidByRowId as $id => $sbid) {
            $normalized[$id] = self::suggestedSbidStorageValue($sbid);
        }
        foreach (array_chunk($normalized, 100, true) as $chunk) {
            $cases = [];
            $bindings = [];
            $ids = [];
            foreach ($chunk as $id => $sbid) {
                $ids[] = $id;
                if ($sbid === null) {
                    $cases[] = 'WHEN ? THEN NULL';
                    $bindings[] = $id;
                } else {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $sbid;
                }
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            DB::update(
                'UPDATE `'.$table.'` SET `sbid` = CASE `id` '.implode(' ', $cases).' ELSE `sbid` END WHERE `id` IN ('.$placeholders.')',
                array_merge($bindings, $ids)
            );
        }
    }

    /**
     * @param  array<int, string>  $campaignIds
     * @return array<string, array{id: int|string, sbid: mixed}>
     */
    private static function latestDailyRowIds(string $table, array $campaignIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($campaignIds)), 200) as $chunk) {
            $chunk = array_values(array_filter($chunk, static fn ($id) => trim((string) $id) !== ''));
            if ($chunk === []) {
                continue;
            }
            $maxes = DB::table($table)
                ->select('campaign_id', DB::raw('MAX(report_date_range) as latest'))
                ->whereIn('campaign_id', $chunk)
                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                ->groupBy('campaign_id')
                ->get();
            if ($maxes->isEmpty()) {
                continue;
            }
            $rows = DB::table($table)
                ->select('id', 'campaign_id', 'sbid')
                ->where(function ($q) use ($maxes) {
                    foreach ($maxes as $max) {
                        $q->orWhere(function ($w) use ($max) {
                            $w->where('campaign_id', $max->campaign_id)
                                ->where('report_date_range', $max->latest);
                        });
                    }
                })
                ->get();
            foreach ($rows as $row) {
                $out[(string) $row->campaign_id] = [
                    'id' => $row->id,
                    'sbid' => $row->sbid,
                ];
            }
        }

        return $out;
    }

    /**
     * Keep L1/L7/L30 and the latest daily row on the suggestion that will be pushed.
     */
    public static function persistSuggestedSbidForCampaign(string $table, string $campaignId, float $sbid): void
    {
        $campaignId = trim($campaignId);
        $stored = self::suggestedSbidStorageValue($sbid);
        if ($campaignId === '' || $stored === null || ! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return;
        }
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sbid')) {
            return;
        }
        $latest = DB::table($table)
            ->where('campaign_id', $campaignId)
            ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
            ->max('report_date_range');
        $ranges = ['L1', 'L7', 'L30'];
        if (is_string($latest) && $latest !== '') {
            $ranges[] = $latest;
        }
        DB::table($table)
            ->where('campaign_id', $campaignId)
            ->whereIn('report_date_range', $ranges)
            ->where(function ($q) use ($stored) {
                $q->whereNull('sbid')
                    ->orWhere('sbid', '=', '')
                    ->orWhereRaw('ROUND(`sbid` + 0, 2) <> ?', [(float) $stored]);
            })
            ->update(['sbid' => $stored]);
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
     * - Both below util_low: CPC1×m1, else CPC2×m2, else CPC3×m7, else Avg CPC + 0.10 when Avg CPC is available, else both_low_fallback when all CPCs are zero.
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
        ?float $costPerClickFallback = null,
        ?float $avgCpc = null
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
            if ($avgCpc !== null && $avgCpc > 0) {
                return ['sbid' => round($avgCpc + 0.10, 2), 'band' => 'under'];
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
