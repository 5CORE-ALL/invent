<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lbid on the grid is last_sbid. A new calendar-day row is often blank even
 * when L1/L30 or an older day already has the Amazon bid.
 */
final class AmazonAdsStoredLiveBid
{
    public static function isBlank(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (! is_numeric($value)) {
            return true;
        }
        $n = (float) $value;

        return ! is_finite($n) || $n <= 0;
    }

    /**
     * Newest daily bid wins, then L1, then L30, then L7.
     *
     * @param  list<array{report_date_range?: mixed, last_sbid?: mixed}>  $candidates
     */
    public static function pick(array $candidates): ?float
    {
        $best = null;
        $bestKey = null;
        foreach ($candidates as $row) {
            if (! is_array($row) || self::isBlank($row['last_sbid'] ?? null)) {
                continue;
            }
            $range = trim((string) ($row['report_date_range'] ?? ''));
            $key = self::rankKey($range);
            if ($bestKey !== null && $key >= $bestKey) {
                continue;
            }
            $bestKey = $key;
            $best = round((float) $row['last_sbid'], 2);
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function fillRows(string $table, array $rows): array
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return $rows;
        }
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'last_sbid') || ! Schema::hasColumn($table, 'campaign_id')) {
            return $rows;
        }
        $need = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($cid !== '' && self::isBlank($row['last_sbid'] ?? null)) {
                $need[$cid] = true;
            }
        }
        if ($need === []) {
            return $rows;
        }
        $byCampaign = [];
        foreach (array_chunk(array_keys($need), 200) as $chunk) {
            $found = DB::table($table)
                ->whereIn('campaign_id', $chunk)
                ->whereNotNull('last_sbid')
                ->where('last_sbid', '!=', '')
                ->whereRaw('(last_sbid + 0) > 0')
                ->get(['campaign_id', 'report_date_range', 'last_sbid']);
            foreach ($found as $stored) {
                $cid = trim((string) $stored->campaign_id);
                $byCampaign[$cid][] = [
                    'report_date_range' => (string) $stored->report_date_range,
                    'last_sbid' => $stored->last_sbid,
                ];
            }
        }
        foreach ($rows as &$row) {
            if (! is_array($row) || ! self::isBlank($row['last_sbid'] ?? null)) {
                continue;
            }
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            $picked = self::pick($byCampaign[$cid] ?? []);
            if ($picked !== null) {
                $row['last_sbid'] = $picked;
            }
        }
        unset($row);

        return $rows;
    }

    private static function rankKey(string $range): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $range) === 1) {
            return '0:'.sprintf('%s', str_pad((string) (99999999 - (int) str_replace('-', '', $range)), 8, '0', STR_PAD_LEFT));
        }

        $group = match ($range) {
            'L1' => '1',
            'L30' => '2',
            'L7' => '3',
            default => '4',
        };

        return $group.':'.$range;
    }
}
