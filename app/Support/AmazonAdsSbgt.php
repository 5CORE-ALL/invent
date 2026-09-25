<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grid SBGT on /amazon-ads/all: Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews + Bgt Dil.
 * Amazon daily budget cannot be $0, so an explicit 0 (BGT ACOS band or a 0 sum) is not
 * pushable — callers pause the campaign instead.
 */
final class AmazonAdsSbgt
{
    /**
     * Grid SBGT. Null parts count as 0 in the sum. All-missing → null.
     * Explicit BGT ACOS of 0 zeros the total (pause — $0 will not push).
     */
    public static function sumFromParts(mixed $bgtViews, mixed $bgtCvr, mixed $bgtAcos, mixed $bgtPrc = null, mixed $bgtReviews = null, mixed $bgtDil = null): ?int
    {
        if (self::isExplicitZero($bgtAcos)) {
            return 0;
        }

        $has = false;
        $sum = 0;
        foreach ([$bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtReviews, $bgtDil] as $part) {
            if ($part === null || $part === '') {
                continue;
            }
            if (! is_numeric($part)) {
                continue;
            }
            $has = true;
            $sum += (int) $part;
        }
        if (! $has) {
            return null;
        }

        return $sum < 1 ? 0 : $sum;
    }

    public static function isExplicitZero(mixed $raw): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }
        if (! is_numeric($raw)) {
            return false;
        }

        return (int) $raw === 0;
    }

    /**
     * Daily budget dollars Amazon will accept (whole dollars 1–9999). 0 is not pushable.
     */
    public static function parsePushableBudget(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (int) $raw;

        return ($n >= 1 && $n <= 9999) ? $n : null;
    }

    /**
     * Same storage shape as SBID (two decimals). 0 is kept — it is the pause suggestion.
     */
    public static function storageValue(mixed $computed): ?string
    {
        if ($computed === null || $computed === '' || ! is_numeric($computed)) {
            return null;
        }
        $n = round((float) $computed, 2);
        if (! is_finite($n) || $n < 0) {
            return null;
        }

        return number_format($n, 2, '.', '');
    }

    public static function storedMatches(mixed $stored, ?string $want): bool
    {
        return self::storageValue($stored) === $want;
    }

    /**
     * Write the SBGT cell onto the visible report row and the latest daily row.
     * Does not change the live budget (campaignBudgetAmount).
     *
     * @param  array<int|string, float|int|string|null>  $sbgtByRowId
     * @param  array<string, float|int|string|null>  $sbgtByCampaignId
     */
    public static function persistByRowId(string $table, array $sbgtByRowId, array $sbgtByCampaignId = []): void
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return;
        }
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sbgt')) {
            return;
        }
        if ($sbgtByCampaignId !== []) {
            foreach (self::latestDailyRows($table, array_keys($sbgtByCampaignId)) as $campaignId => $latest) {
                $want = self::storageValue($sbgtByCampaignId[$campaignId] ?? null);
                if (! self::storedMatches($latest['sbgt'] ?? null, $want)) {
                    $sbgtByRowId[$latest['id']] = $want;
                }
            }
        }
        if ($sbgtByRowId === []) {
            return;
        }
        $normalized = [];
        foreach ($sbgtByRowId as $id => $sbgt) {
            $normalized[$id] = self::storageValue($sbgt);
        }
        foreach (array_chunk($normalized, 100, true) as $chunk) {
            $cases = [];
            $bindings = [];
            $ids = [];
            foreach ($chunk as $id => $sbgt) {
                $ids[] = $id;
                if ($sbgt === null) {
                    $cases[] = 'WHEN ? THEN NULL';
                    $bindings[] = $id;
                } else {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $sbgt;
                }
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            DB::update(
                'UPDATE `'.$table.'` SET `sbgt` = CASE `id` '.implode(' ', $cases).' ELSE `sbgt` END WHERE `id` IN ('.$placeholders.')',
                array_merge($bindings, $ids)
            );
        }
    }

    /**
     * @param  array<int, string>  $campaignIds
     * @return array<string, array{id: int|string, sbgt: mixed}>
     */
    private static function latestDailyRows(string $table, array $campaignIds): array
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
                ->select('id', 'campaign_id', 'sbgt')
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
                    'sbgt' => $row->sbgt,
                ];
            }
        }

        return $out;
    }
}
