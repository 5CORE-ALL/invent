<?php

namespace App\Support;

use App\Models\AmazonAdsCampaignSku;
use App\Models\AmazonDatasheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Persist and resolve advertised SKUs for /amazon-ads/all Campaign SKUs + Reviews pause.
 *
 * Sources, in preference order:
 * 1. Amazon SP product ads (real ad_id)
 * 2. Amazon SB ads (ad_id prefix sb:)
 * 3. Campaign-name fallback (ad_id prefix name:) — PARENT … → product_master children
 */
final class AmazonAdsCampaignSkuSync
{
    public const NAME_AD_PREFIX = 'name:';

    public const SB_AD_PREFIX = 'sb:';

    /**
     * @return list<array{sku: string, asin: ?string, state: ?string, source: string}>
     */
    public static function listedSkusForCampaign(string $campaignId): array
    {
        $cid = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        if ($cid === '' || ! Schema::hasTable('amazon_ads_campaign_skus')) {
            return [];
        }

        $rows = AmazonAdsCampaignSku::query()
            ->where('campaign_id', $cid)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('sku')
            ->get(['sku', 'asin', 'state', 'ad_id']);

        $real = [];
        $named = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $item = [
                'sku' => $sku,
                'asin' => $row->asin ? (string) $row->asin : null,
                'state' => $row->state ? (string) $row->state : null,
                'source' => self::sourceFromAdId((string) ($row->ad_id ?? '')),
            ];
            if (str_starts_with((string) ($row->ad_id ?? ''), self::NAME_AD_PREFIX)) {
                $named[] = $item;
            } else {
                $real[] = $item;
            }
        }

        return self::dedupeSkus($real !== [] ? $real : $named);
    }

    /**
     * Table lookup, then campaign-name SKUs when Amazon has no product ads (typical for SB).
     *
     * @return array{skus: list<array{sku: string, asin: ?string, state: ?string, source: string}>, source: ?string, campaign_name: string}
     */
    public static function resolveForCampaign(string $campaignId, string $campaignName = ''): array
    {
        $cid = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        $name = trim($campaignName);
        $meta = self::resolveCampaignMeta($cid);
        if ($name === '') {
            $name = $meta['name'];
        }
        $state = $meta['state'];

        $listed = $cid !== '' ? self::listedSkusForCampaign($cid) : [];
        if ($listed !== []) {
            return [
                'skus' => $listed,
                'source' => $listed[0]['source'] ?? 'product_ads',
                'campaign_name' => $name !== '' ? $name : (string) ($listed[0]['sku'] ?? ''),
            ];
        }

        $skus = AmazonAdsCampaignSkuMetrics::advertisedSkusFromCampaignName($name);
        if ($cid !== '' && $skus !== []) {
            self::persistNameDerived(self::defaultProfileId(), $cid, $name, $skus, $state);
            $listed = self::listedSkusForCampaign($cid);
            if ($listed !== []) {
                return [
                    'skus' => $listed,
                    'source' => 'campaign_name',
                    'campaign_name' => $name,
                ];
            }
        }

        return [
            'skus' => [],
            'source' => null,
            'campaign_name' => $name,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ads
     */
    public static function persistSpAds(string $profileId, array $ads): int
    {
        $profileId = trim($profileId) !== '' ? trim($profileId) : 'default';
        $now = now();
        $payload = [];
        foreach ($ads as $ad) {
            if (! is_array($ad)) {
                continue;
            }
            $adId = preg_replace('/\D+/', '', trim((string) ($ad['adId'] ?? $ad['ad_id'] ?? ''))) ?: '';
            $campaignId = preg_replace('/\D+/', '', trim((string) ($ad['campaignId'] ?? $ad['campaign_id'] ?? ''))) ?: '';
            $sku = trim((string) ($ad['sku'] ?? ''));
            if ($adId === '' || $sku === '') {
                continue;
            }
            $payload[] = [
                'profile_id' => $profileId,
                'ad_id' => $adId,
                'campaign_id' => $campaignId !== '' ? $campaignId : null,
                'campaign_name' => null,
                'ad_group_id' => trim((string) ($ad['adGroupId'] ?? $ad['ad_group_id'] ?? '')) ?: null,
                'sku' => $sku,
                'asin' => strtoupper(trim((string) ($ad['asin'] ?? ''))) ?: null,
                'state' => strtoupper(trim((string) ($ad['state'] ?? ''))) ?: null,
                'pulled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return self::upsertRows($payload);
    }

    /**
     * @param  list<array<string, mixed>>  $ads
     */
    public static function persistSbAds(string $profileId, array $ads): int
    {
        $profileId = trim($profileId) !== '' ? trim($profileId) : 'default';
        $asins = [];
        $parsed = [];
        foreach ($ads as $ad) {
            if (! is_array($ad)) {
                continue;
            }
            $adId = preg_replace('/\D+/', '', trim((string) ($ad['adId'] ?? $ad['ad_id'] ?? ''))) ?: '';
            $campaignId = preg_replace('/\D+/', '', trim((string) ($ad['campaignId'] ?? $ad['campaign_id'] ?? ''))) ?: '';
            if ($adId === '') {
                continue;
            }
            $adAsins = self::extractAsinsFromSbAd($ad);
            foreach ($adAsins as $asin) {
                $asins[$asin] = true;
            }
            $parsed[] = [
                'ad_id' => $adId,
                'campaign_id' => $campaignId,
                'ad_group_id' => trim((string) ($ad['adGroupId'] ?? $ad['ad_group_id'] ?? '')) ?: null,
                'state' => strtoupper(trim((string) ($ad['state'] ?? ''))) ?: null,
                'asins' => $adAsins,
            ];
        }

        $skuByAsin = self::skusByAsins(array_keys($asins));
        $now = now();
        $payload = [];
        foreach ($parsed as $ad) {
            foreach ($ad['asins'] as $asin) {
                $sku = $skuByAsin[$asin] ?? '';
                if ($sku === '') {
                    continue;
                }
                $payload[] = [
                    'profile_id' => $profileId,
                    'ad_id' => self::sbAdId($ad['ad_id'], $sku),
                    'campaign_id' => $ad['campaign_id'] !== '' ? $ad['campaign_id'] : null,
                    'campaign_name' => null,
                    'ad_group_id' => $ad['ad_group_id'],
                    'sku' => $sku,
                    'asin' => $asin,
                    'state' => $ad['state'],
                    'pulled_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return self::upsertRows($payload);
    }

    /**
     * @param  list<string>  $skus
     */
    public static function persistNameDerived(
        string $profileId,
        string $campaignId,
        string $campaignName,
        array $skus,
        ?string $state = null
    ): int {
        $cid = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        $profileId = trim($profileId) !== '' ? trim($profileId) : 'default';
        if ($cid === '') {
            return 0;
        }
        $asinBySku = self::asinsBySkus($skus);
        $now = now();
        $st = $state !== null && $state !== '' ? strtoupper(trim($state)) : null;
        $payload = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $key = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
            $payload[] = [
                'profile_id' => $profileId,
                'ad_id' => self::nameAdId($cid, $sku),
                'campaign_id' => $cid,
                'campaign_name' => $campaignName !== '' ? $campaignName : null,
                'ad_group_id' => null,
                'sku' => $sku,
                'asin' => $asinBySku[$key] ?? $asinBySku[$sku] ?? null,
                'state' => $st,
                'pulled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return self::upsertRows($payload);
    }

    public static function backfillCampaignNames(): int
    {
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            return 0;
        }
        $cids = AmazonAdsCampaignSku::query()
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->distinct()
            ->pluck('campaign_id')
            ->all();
        if ($cids === []) {
            return 0;
        }

        $updated = 0;
        foreach (array_chunk($cids, 300) as $chunk) {
            $byCid = [];
            foreach (['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $names = DB::table($table)
                    ->whereIn('campaign_id', $chunk)
                    ->orderByDesc('id')
                    ->get(['campaign_id', 'campaignName']);
                foreach ($names as $row) {
                    $cid = (string) ($row->campaign_id ?? '');
                    if ($cid === '' || isset($byCid[$cid])) {
                        continue;
                    }
                    $byCid[$cid] = trim((string) ($row->campaignName ?? ''));
                }
            }
            foreach ($byCid as $cid => $name) {
                if ($name === '') {
                    continue;
                }
                $updated += AmazonAdsCampaignSku::query()
                    ->where('campaign_id', $cid)
                    ->where(function ($q) use ($name) {
                        $q->whereNull('campaign_name')->orWhere('campaign_name', '!=', $name);
                    })
                    ->update(['campaign_name' => $name]);
            }
        }

        return $updated;
    }

    /**
     * For every SP/SB campaign with no stored SKU, persist campaign-name SKUs.
     */
    public static function backfillMissingFromCampaignNames(string $profileId): int
    {
        $campaigns = [];
        foreach (['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'campaign_id')) {
                continue;
            }
            $q = DB::table($table)
                ->whereNotNull('campaign_id')
                ->where('campaign_id', '!=', '');
            if (Schema::hasColumn($table, 'report_date_range')) {
                $q->where('report_date_range', 'L30');
            }
            $rows = $q->orderByDesc('id')->get(['campaign_id', 'campaignName', 'campaignStatus']);
            foreach ($rows as $row) {
                $cid = preg_replace('/\D+/', '', trim((string) ($row->campaign_id ?? ''))) ?: '';
                if ($cid === '' || isset($campaigns[$cid])) {
                    continue;
                }
                $campaigns[$cid] = [
                    'name' => trim((string) ($row->campaignName ?? '')),
                    'state' => strtoupper(trim((string) ($row->campaignStatus ?? ''))) ?: null,
                ];
            }
        }
        if ($campaigns === [] || ! Schema::hasTable('amazon_ads_campaign_skus')) {
            return 0;
        }

        $have = [];
        foreach (AmazonAdsCampaignSku::query()
            ->whereNotNull('campaign_id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->distinct()
            ->pluck('campaign_id') as $cid) {
            $id = preg_replace('/\D+/', '', trim((string) $cid)) ?: '';
            if ($id !== '') {
                $have[$id] = true;
            }
        }

        $n = 0;
        foreach ($campaigns as $cid => $meta) {
            if (isset($have[$cid])) {
                continue;
            }
            $skus = AmazonAdsCampaignSkuMetrics::advertisedSkusFromCampaignName($meta['name']);
            if ($skus === []) {
                continue;
            }
            $n += self::persistNameDerived($profileId, $cid, $meta['name'], $skus, $meta['state']);
        }

        return $n;
    }

    public static function dropNameDerivedWhereRealAdsExist(): int
    {
        if (! Schema::hasTable('amazon_ads_campaign_skus')) {
            return 0;
        }
        $realCids = AmazonAdsCampaignSku::query()
            ->where('ad_id', 'not like', self::NAME_AD_PREFIX.'%')
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->distinct()
            ->pluck('campaign_id');
        if ($realCids->isEmpty()) {
            return 0;
        }

        return AmazonAdsCampaignSku::query()
            ->whereIn('campaign_id', $realCids)
            ->where('ad_id', 'like', self::NAME_AD_PREFIX.'%')
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $ad
     * @return list<string>
     */
    public static function extractAsinsFromSbAd(array $ad): array
    {
        $found = [];
        $walk = static function ($node) use (&$walk, &$found): void {
            if (is_string($node)) {
                $u = strtoupper(trim($node));
                if (preg_match('/^B0[A-Z0-9]{8}$/', $u) === 1) {
                    $found[$u] = true;
                }

                return;
            }
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                $lk = strtolower((string) $k);
                if (in_array($lk, ['asin', 'asin1'], true) && is_string($v) && trim($v) !== '') {
                    $found[strtoupper(trim($v))] = true;
                }
                if (in_array($lk, ['asins', 'asinlist'], true) && is_array($v)) {
                    foreach ($v as $a) {
                        if (is_string($a) && trim($a) !== '') {
                            $found[strtoupper(trim($a))] = true;
                        }
                    }
                }
                $walk($v);
            }
        };
        $walk($ad);

        return array_keys($found);
    }

    public static function nameAdId(string $campaignId, string $sku): string
    {
        $raw = self::NAME_AD_PREFIX.$campaignId.':'.$sku;
        if (strlen($raw) <= 190) {
            return $raw;
        }

        return self::NAME_AD_PREFIX.$campaignId.':'.substr(sha1($sku), 0, 16);
    }

    /**
     * @return array{channel: 'sp'|'sb'|null, ad_id: string}
     */
    public static function amazonAdRef(string $storedAdId): array
    {
        $storedAdId = trim($storedAdId);
        if ($storedAdId === '' || str_starts_with($storedAdId, self::NAME_AD_PREFIX)) {
            return ['channel' => null, 'ad_id' => ''];
        }
        if (str_starts_with($storedAdId, self::SB_AD_PREFIX)) {
            $rest = substr($storedAdId, strlen(self::SB_AD_PREFIX));
            $amazonId = preg_replace('/\D+/', '', explode(':', $rest, 2)[0] ?? '') ?: '';

            return ['channel' => $amazonId !== '' ? 'sb' : null, 'ad_id' => $amazonId];
        }
        $amazonId = preg_replace('/\D+/', '', $storedAdId) ?: '';

        return ['channel' => $amazonId !== '' ? 'sp' : null, 'ad_id' => $amazonId];
    }

    public static function sbAdId(string $adId, string $sku): string
    {
        $raw = self::SB_AD_PREFIX.$adId.':'.$sku;
        if (strlen($raw) <= 190) {
            return $raw;
        }

        return self::SB_AD_PREFIX.$adId.':'.substr(sha1($sku), 0, 16);
    }

    /**
     * @param  list<string>  $asins
     * @return array<string, string>  ASIN => SKU
     */
    public static function skusByAsins(array $asins): array
    {
        $want = [];
        foreach ($asins as $asin) {
            $a = strtoupper(trim((string) $asin));
            if ($a !== '') {
                $want[$a] = true;
            }
        }
        if ($want === [] || ! Schema::hasTable('amazon_datsheets')) {
            return [];
        }
        $list = array_keys($want);
        $ph = implode(',', array_fill(0, count($list), '?'));
        $rows = AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('asin')
            ->whereRaw('UPPER(TRIM(asin)) IN ('.$ph.')', $list)
            ->get(['asin', 'sku']);
        $out = [];
        foreach ($rows as $row) {
            $asin = strtoupper(trim((string) ($row->asin ?? '')));
            $sku = trim((string) ($row->sku ?? ''));
            if ($asin === '' || $sku === '' || isset($out[$asin])) {
                continue;
            }
            $out[$asin] = $sku;
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>  uppercase SKU => ASIN
     */
    public static function asinsBySkus(array $skus): array
    {
        $want = [];
        foreach ($skus as $sku) {
            $k = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $sku)));
            if ($k !== '') {
                $want[$k] = true;
            }
        }
        if ($want === [] || ! Schema::hasTable('amazon_datsheets')) {
            return [];
        }
        $spaceKeys = array_keys($want);
        $compactKeys = [];
        foreach ($spaceKeys as $k) {
            $ck = AmazonDatasheet::normalizeSkuForLookup($k);
            if ($ck !== '') {
                $compactKeys[] = $ck;
            }
        }
        $compactKeys = array_values(array_unique($compactKeys));
        $rows = AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('asin')
            ->where(function ($q) use ($spaceKeys, $compactKeys) {
                $ph = implode(',', array_fill(0, count($spaceKeys), '?'));
                $q->whereRaw('UPPER(TRIM(sku)) IN ('.$ph.')', $spaceKeys);
                if ($compactKeys !== []) {
                    $ph2 = implode(',', array_fill(0, count($compactKeys), '?'));
                    $q->orWhereRaw('UPPER(REPLACE(REPLACE(TRIM(sku), " ", ""), CHAR(9), "")) IN ('.$ph2.')', $compactKeys);
                }
            })
            ->get(['sku', 'asin']);
        $out = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) ($row->sku ?? ''))));
            $asin = strtoupper(trim((string) ($row->asin ?? '')));
            if ($sku === '' || $asin === '' || isset($out[$sku])) {
                continue;
            }
            $out[$sku] = $asin;
        }

        return $out;
    }

    /**
     * @return array{name: string, state: ?string}
     */
    public static function resolveCampaignMeta(string $campaignId): array
    {
        $cid = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        if ($cid === '') {
            return ['name' => '', 'state' => null];
        }
        foreach (['amazon_sb_campaign_reports', 'amazon_sp_campaign_reports'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $row = DB::table($table)
                ->where('campaign_id', $cid)
                ->orderByDesc('id')
                ->first(['campaignName', 'campaignStatus']);
            if ($row === null) {
                continue;
            }
            $name = trim((string) ($row->campaignName ?? ''));
            $state = strtoupper(trim((string) ($row->campaignStatus ?? ''))) ?: null;
            if ($name !== '' || $state !== null) {
                return ['name' => $name, 'state' => $state];
            }
        }

        $stored = Schema::hasTable('amazon_ads_campaign_skus')
            ? AmazonAdsCampaignSku::query()
                ->where('campaign_id', $cid)
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->value('campaign_name')
            : null;

        return ['name' => $stored ? (string) $stored : '', 'state' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function upsertRows(array $rows): int
    {
        if ($rows === [] || ! Schema::hasTable('amazon_ads_campaign_skus')) {
            return 0;
        }
        $upserted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            try {
                AmazonAdsCampaignSku::upsert(
                    $chunk,
                    ['profile_id', 'ad_id'],
                    ['campaign_id', 'campaign_name', 'ad_group_id', 'sku', 'asin', 'state', 'pulled_at', 'updated_at']
                );
                $upserted += count($chunk);
            } catch (\Throwable $e) {
                Log::warning('amazon_ads_campaign_skus upsert failed', ['error' => $e->getMessage()]);
            }
        }

        return $upserted;
    }

    /**
     * @param  list<array{sku: string, asin: ?string, state: ?string, source: string}>  $rows
     * @return list<array{sku: string, asin: ?string, state: ?string, source: string}>
     */
    private static function dedupeSkus(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '' || isset($seen[$sku])) {
                continue;
            }
            $seen[$sku] = true;
            $out[] = $row;
        }

        return $out;
    }

    private static function sourceFromAdId(string $adId): string
    {
        if (str_starts_with($adId, self::NAME_AD_PREFIX)) {
            return 'campaign_name';
        }
        if (str_starts_with($adId, self::SB_AD_PREFIX)) {
            return 'sb_ads';
        }

        return 'product_ads';
    }

    private static function defaultProfileId(): string
    {
        $raw = trim((string) config('services.amazon_ads.profile_ids', ''));
        if ($raw === '') {
            return 'default';
        }
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];

        return trim((string) ($parts[0] ?? $raw)) ?: 'default';
    }
}
