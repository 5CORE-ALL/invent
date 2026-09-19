<?php

namespace App\Support;

use App\Models\AmazonAdsAdGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Map Amazon ad-group list payloads into amazon_ads_ad_groups rows.
 */
class AmazonAdsAdGroupSync
{
    /**
     * @param  list<array<string, mixed>>  $adGroups
     * @param  array<string, string>  $campaignNames
     * @return list<array<string, mixed>>
     */
    public static function rowsFromAmazon(
        string $profileId,
        string $adType,
        array $adGroups,
        array $campaignNames = []
    ): array {
        $profileId = trim($profileId) !== '' ? trim($profileId) : 'default';
        $adType = strtoupper(trim($adType));
        if ($adType === 'SP') {
            $adType = AmazonAdsAdGroup::AD_TYPE_SP;
        } elseif ($adType === 'SB') {
            $adType = AmazonAdsAdGroup::AD_TYPE_SB;
        }
        $now = Carbon::now();
        $out = [];

        foreach ($adGroups as $ag) {
            if (! is_array($ag)) {
                continue;
            }
            $adGroupId = preg_replace('/\D+/', '', trim((string) ($ag['adGroupId'] ?? $ag['ad_group_id'] ?? ''))) ?: '';
            if ($adGroupId === '') {
                continue;
            }
            $campaignId = preg_replace('/\D+/', '', trim((string) ($ag['campaignId'] ?? $ag['campaign_id'] ?? ''))) ?: '';
            $name = trim((string) ($ag['name'] ?? $ag['adGroupName'] ?? ''));
            $campaignName = trim((string) ($ag['campaignName'] ?? ''));
            if ($campaignName === '' && $campaignId !== '') {
                $campaignName = trim((string) ($campaignNames[$campaignId] ?? ''));
            }

            $out[] = [
                'profile_id' => $profileId,
                'ad_type' => $adType !== '' ? $adType : AmazonAdsAdGroup::AD_TYPE_SP,
                'ad_group_id' => $adGroupId,
                'campaign_id' => $campaignId !== '' ? $campaignId : null,
                'campaignName' => $campaignName !== '' ? $campaignName : null,
                'adGroupName' => $name !== '' ? $name : null,
                'state' => strtoupper(trim((string) ($ag['state'] ?? ''))) ?: null,
                'defaultBid' => self::defaultBid($ag),
                'pulled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @return array<string, string>
     */
    public static function namesFromCampaigns(array $campaigns): array
    {
        $out = [];
        foreach ($campaigns as $campaign) {
            if (! is_array($campaign)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', trim((string) ($campaign['campaignId'] ?? $campaign['campaign_id'] ?? ''))) ?: '';
            $name = trim((string) ($campaign['name'] ?? $campaign['campaignName'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }
            $out[$id] = $name;
        }

        return $out;
    }

    /**
     * Latest campaign_id → campaignName from SP/SB report tables.
     *
     * @return array<string, string>
     */
    public static function namesFromReports(): array
    {
        $out = [];
        foreach (['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $map = DB::table($table)
                    ->whereNotNull('campaign_id')
                    ->where('campaign_id', '!=', '')
                    ->whereNotNull('campaignName')
                    ->where('campaignName', '!=', '')
                    ->pluck('campaignName', 'campaign_id');
            } catch (\Throwable) {
                continue;
            }
            foreach ($map as $campaignId => $name) {
                $id = preg_replace('/\D+/', '', trim((string) $campaignId)) ?: '';
                $label = trim((string) $name);
                if ($id === '' || $label === '' || isset($out[$id])) {
                    continue;
                }
                $out[$id] = $label;
            }
        }

        return $out;
    }

    /**
     * Fill blank campaignName from the name map (report + live campaign lists).
     *
     * @param  array<string, string>  $campaignNames
     */
    public static function backfillCampaignNames(array $campaignNames): int
    {
        if ($campaignNames === [] || ! Schema::hasTable('amazon_ads_ad_groups')) {
            return 0;
        }

        $updated = 0;
        AmazonAdsAdGroup::query()
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('campaignName')->orWhere('campaignName', '');
            })
            ->select(['id', 'campaign_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($campaignNames, &$updated) {
                foreach ($rows as $row) {
                    $id = preg_replace('/\D+/', '', trim((string) $row->campaign_id)) ?: '';
                    $name = $id !== '' ? trim((string) ($campaignNames[$id] ?? '')) : '';
                    if ($name === '') {
                        continue;
                    }
                    AmazonAdsAdGroup::query()->where('id', $row->id)->update(['campaignName' => $name]);
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function persist(array $rows): int
    {
        if ($rows === [] || ! Schema::hasTable('amazon_ads_ad_groups')) {
            return 0;
        }

        $upserted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            try {
                AmazonAdsAdGroup::upsert(
                    $chunk,
                    ['profile_id', 'ad_type', 'ad_group_id'],
                    ['campaign_id', 'campaignName', 'adGroupName', 'state', 'defaultBid', 'pulled_at', 'updated_at']
                );
                $upserted += count($chunk);
            } catch (\Throwable $e) {
                Log::warning('amazon_ads_ad_groups upsert failed', ['error' => $e->getMessage()]);
            }
        }

        return $upserted;
    }

    /**
     * @param  list<string>  $seenAdGroupIds
     */
    public static function prune(string $profileId, string $adType, array $seenAdGroupIds): int
    {
        if (! Schema::hasTable('amazon_ads_ad_groups')) {
            return 0;
        }

        $profileId = trim($profileId) !== '' ? trim($profileId) : 'default';
        $query = AmazonAdsAdGroup::query()
            ->where('profile_id', $profileId)
            ->where('ad_type', $adType);

        if ($seenAdGroupIds === []) {
            return (int) $query->delete();
        }

        $deleted = 0;
        foreach (array_chunk($query->pluck('ad_group_id')->all(), 5000) as $chunk) {
            $stale = array_values(array_diff($chunk, $seenAdGroupIds));
            if ($stale === []) {
                continue;
            }
            $deleted += AmazonAdsAdGroup::query()
                ->where('profile_id', $profileId)
                ->where('ad_type', $adType)
                ->whereIn('ad_group_id', $stale)
                ->delete();
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $adGroup
     */
    public static function defaultBid(array $adGroup): ?float
    {
        foreach (['defaultBid', 'default_bid', 'bid'] as $key) {
            $raw = $adGroup[$key] ?? null;
            if (is_array($raw)) {
                $raw = $raw['amount'] ?? $raw['bid'] ?? $raw['defaultBid'] ?? null;
            }
            if (is_numeric($raw)) {
                return round((float) $raw, 2);
            }
        }

        return null;
    }
}
