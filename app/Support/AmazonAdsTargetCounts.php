<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stored keyword / target counts for ad products that are not in the SP targeting report.
 */
class AmazonAdsTargetCounts
{
    public const TABLE = 'amazon_ads_target_counts';

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, int> campaign id => count (campaigns with no stored row are omitted)
     */
    public static function map(string $adProduct, array $campaignIds, string $column = 'targets'): array
    {
        $column = $column === 'n_targets' ? 'n_targets' : 'targets';
        $ids = self::normalizeIds($campaignIds);
        if ($ids === [] || ! Schema::hasTable(self::TABLE)) {
            return [];
        }

        $map = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::table(self::TABLE)
                ->where('ad_product', $adProduct)
                ->whereIn('campaign_id', $chunk)
                ->get(['campaign_id', $column]);
            foreach ($rows as $row) {
                $value = $row->{$column} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $map[trim((string) $row->campaign_id)] = (int) $value;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function upsert(string $adProduct, array $counts, string $column = 'targets'): void
    {
        $column = $column === 'n_targets' ? 'n_targets' : 'targets';
        if ($counts === [] || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($counts as $campaignId => $count) {
            $id = trim((string) $campaignId);
            if ($id === '') {
                continue;
            }
            $rows[] = [
                'ad_product' => $adProduct,
                'campaign_id' => $id,
                $column => max(0, (int) $count),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table(self::TABLE)->upsert(
                $chunk,
                ['ad_product', 'campaign_id'],
                [$column, 'updated_at']
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $counts
     * @param  array<string, true>  $seen
     */
    public static function tally(array $rows, array &$counts, array &$seen): void
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaignId'] ?? $row['campaign_id'] ?? ''));
            if ($cid === '' || ! array_key_exists($cid, $counts)) {
                continue;
            }
            $entityId = trim((string) ($row['keywordId'] ?? $row['targetId'] ?? $row['keyword_id'] ?? $row['target_id'] ?? ''));
            $dedupe = $cid.'|'.($entityId !== '' ? $entityId : md5((string) json_encode($row)));
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $counts[$cid]++;
        }
    }

    /**
     * @param  list<string>  $campaignIds
     * @return list<string>
     */
    public static function normalizeIds(array $campaignIds): array
    {
        $ids = [];
        foreach ($campaignIds as $id) {
            $s = trim((string) $id);
            if ($s !== '') {
                $ids[$s] = $s;
            }
        }

        return array_values($ids);
    }
}
