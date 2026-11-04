<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * BGT CAP rules on /amazon-ads/all.
 *
 * Per ad type (KW, PT, HL) and ACOS colour (yellow, red — same colours as /amazon-ads/missing, lifetime
 * data, ad types with lifetime spend under $30 ignored)
 * the suggested budget (SBGT) of the parent's linked campaigns is capped at an editable $ amount
 * (default $1). A cap only lowers a budget; an SBGT of 0 (pause) stays 0. A cap of $0 sets SBGT to 0,
 * which the budget push / live sync treat as "pause the campaign".
 */
final class AmazonAdsBgtCapRule
{
    public const SETTINGS_CHANNEL = 'amazon_ads_bgt_cap_rule';

    public const DEFAULT_CAP = 1;

    public const MAX_CAP = 9999;

    private const TIER_CACHE_KEY = 'amazon_ads_bgt_cap_tiers_v2';

    private const COUNT_CACHE_KEY = 'amazon_ads_bgt_cap_counts_v2';

    /** missing-page ACOS tier => colour name used by this rule */
    private const COLOURS = ['mid' => 'yellow', 'high' => 'red'];

    /** @var array<string, array{key: string, colour: string}>|null */
    private static ?array $tierMemo = null;

    /** @var array{enabled: bool, caps: array<string, array<string, int>>}|null */
    private static ?array $ruleMemo = null;

    /**
     * @return array{enabled: bool, caps: array<string, array{yellow: int, red: int}>}
     */
    public static function defaults(): array
    {
        $caps = [];
        foreach (AmazonAdsAcosPauseRules::keys() as $key) {
            $caps[$key] = ['yellow' => self::DEFAULT_CAP, 'red' => self::DEFAULT_CAP];
        }

        return ['enabled' => false, 'caps' => $caps];
    }

    /**
     * @return array{enabled: bool, caps: array<string, array{yellow: int, red: int}>}
     */
    public static function normalize(mixed $input): array
    {
        $base = self::defaults();
        if (! is_array($input)) {
            return $base;
        }
        $enabled = $input['enabled'] ?? false;
        $base['enabled'] = is_string($enabled)
            ? in_array(strtolower($enabled), ['1', 'true', 'yes', 'on'], true)
            : (bool) $enabled;
        $caps = is_array($input['caps'] ?? null) ? $input['caps'] : [];
        foreach (AmazonAdsAcosPauseRules::keys() as $key) {
            foreach (['yellow', 'red'] as $colour) {
                $raw = $caps[$key][$colour] ?? self::DEFAULT_CAP;
                if (! is_numeric($raw) || ! is_finite((float) $raw)) {
                    throw new \InvalidArgumentException(strtoupper($key).' '.$colour.' cap must be a number.');
                }
                $n = (int) floor((float) $raw);
                if ($n < 0 || $n > self::MAX_CAP) {
                    throw new \InvalidArgumentException(strtoupper($key).' '.$colour.' cap must be a whole dollar amount from 0 to '.self::MAX_CAP.'.');
                }
                $base['caps'][$key][$colour] = $n;
            }
        }

        return $base;
    }

    /**
     * @return array{enabled: bool, caps: array<string, array{yellow: int, red: int}>}
     */
    public static function resolvedRule(): array
    {
        if (self::$ruleMemo !== null) {
            return self::$ruleMemo;
        }
        try {
            $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::SETTINGS_CHANNEL)->first();
            $rule = self::normalize(is_array($row?->visibility) ? $row->visibility : null);
        } catch (\Throwable $e) {
            $rule = self::defaults();
        }

        return self::$ruleMemo = $rule;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(array $input): void
    {
        $rule = self::normalize($input);
        $row = ChannelTabulatorColumnSetting::query()->firstOrNew(['channel_name' => self::SETTINGS_CHANNEL]);
        $row->visibility = $rule;
        $row->column_order = ['bgt_cap'];
        $row->save();
        self::forgetCaches();
    }

    public static function forgetCaches(): void
    {
        self::$ruleMemo = null;
        self::$tierMemo = null;
        try {
            Cache::forget(self::TIER_CACHE_KEY);
            Cache::forget(self::COUNT_CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    public static function enabled(): bool
    {
        return self::resolvedRule()['enabled'];
    }

    /**
     * Cap the SBGT of a campaign when the rule is ON and the campaign's ad type is yellow / red.
     * Only lowers a positive budget.
     */
    public static function apply(?int $sbgt, ?string $campaignName): ?int
    {
        if ($sbgt === null || $sbgt <= 0 || ! self::enabled()) {
            return $sbgt;
        }
        $cap = self::capForCampaign($campaignName);

        return ($cap !== null && $sbgt > $cap) ? $cap : $sbgt;
    }

    public static function capForCampaign(?string $campaignName): ?int
    {
        $norm = AmazonAdsMissingLinks::normalizeCampaignName((string) $campaignName);
        if ($norm === '') {
            return null;
        }
        $hit = self::tierMap()[$norm] ?? null;
        if ($hit === null) {
            return null;
        }

        return self::resolvedRule()['caps'][$hit['key']][$hit['colour']] ?? null;
    }

    /**
     * Normalized campaign name => ad type + colour, for every yellow / red linked campaign.
     * Cached for a few minutes: building it reads the same data as /amazon-ads/missing.
     *
     * @return array<string, array{key: string, colour: string}>
     */
    public static function tierMap(): array
    {
        if (self::$tierMemo !== null) {
            return self::$tierMemo;
        }
        try {
            $map = Cache::remember(self::TIER_CACHE_KEY, now()->addMinutes(10), static function (): array {
                $out = [];
                foreach (AmazonAdsAcosPauseRules::tierCampaigns(AmazonAdsAcosPauseRules::keys()) as $hit) {
                    $colour = self::COLOURS[$hit['tier']] ?? null;
                    if ($colour === null) {
                        continue;
                    }
                    $out[AmazonAdsMissingLinks::normalizeCampaignName($hit['campaign_name'])] = [
                        'key' => $hit['key'],
                        'colour' => $colour,
                    ];
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('Amazon Ads BGT cap: tier map failed', ['error' => $e->getMessage()]);
            $map = [];
        }

        return self::$tierMemo = is_array($map) ? $map : [];
    }

    /**
     * How many campaigns each cap actually lowers (their uncapped SBGT is above the cap).
     *
     * @return array{total: int, counts: array<string, array{yellow: int, red: int}>, matched: array<string, array{yellow: int, red: int}>}
     */
    public static function counts(bool $fresh = false): array
    {
        $rule = self::resolvedRule();
        $cacheKey = self::COUNT_CACHE_KEY.':'.md5(json_encode($rule));
        if (! $fresh) {
            try {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            } catch (\Throwable) {
            }
        }
        if ($fresh) {
            self::$tierMemo = null;
            try {
                Cache::forget(self::TIER_CACHE_KEY);
            } catch (\Throwable) {
            }
        }

        $zero = [];
        foreach (AmazonAdsAcosPauseRules::keys() as $key) {
            $zero[$key] = ['yellow' => 0, 'red' => 0];
        }
        $out = ['total' => 0, 'counts' => $zero, 'matched' => $zero];

        try {
            $map = self::tierMap();
            foreach (['sp', 'sb'] as $channel) {
                $campaigns = self::latestCampaignsByName($channel, $map);
                if ($campaigns === []) {
                    continue;
                }
                $uncapped = AmazonAdsDesiredSbgtResolver::sbgtForCampaigns(
                    array_map(static fn (array $c): array => [
                        'campaign_id' => $c['campaign_id'],
                        'campaignName' => $c['campaign_name'],
                    ], $campaigns),
                    $channel,
                    null,
                    null,
                    false
                );
                foreach ($campaigns as $c) {
                    $hit = $map[$c['norm']];
                    $out['matched'][$hit['key']][$hit['colour']]++;
                    $sbgt = $uncapped[$c['campaign_id']] ?? null;
                    $cap = $rule['caps'][$hit['key']][$hit['colour']] ?? null;
                    if ($sbgt !== null && $cap !== null && $sbgt > $cap) {
                        $out['counts'][$hit['key']][$hit['colour']]++;
                        $out['total']++;
                    }
                }
            }
            Cache::put($cacheKey, $out, now()->addMinutes(10));
        } catch (\Throwable $e) {
            Log::warning('Amazon Ads BGT cap: count failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Latest report row per tiered campaign (not archived) in one channel.
     *
     * @param  'sp'|'sb'  $channel
     * @param  array<string, array{key: string, colour: string}>  $map
     * @return list<array{campaign_id: string, campaign_name: string, norm: string}>
     */
    private static function latestCampaignsByName(string $channel, array $map): array
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        if ($map === [] || ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'campaign_id') || ! Schema::hasColumn($table, 'campaignName')) {
            return [];
        }
        $wantHl = $channel === 'sb';
        $ids = DB::table($table)
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->whereNotNull('campaign_id')
            ->selectRaw('MAX(id) AS max_id')
            ->groupBy('campaignName')
            ->pluck('max_id')
            ->filter()
            ->all();
        $out = [];
        $seen = [];
        $hasStatus = Schema::hasColumn($table, 'campaignStatus');
        foreach (array_chunk($ids, 1000) as $chunk) {
            $cols = ['campaign_id', 'campaignName'];
            if ($hasStatus) {
                $cols[] = 'campaignStatus';
            }
            foreach (DB::table($table)->whereIn('id', $chunk)->get($cols) as $r) {
                $norm = AmazonAdsMissingLinks::normalizeCampaignName((string) $r->campaignName);
                $hit = $map[$norm] ?? null;
                if ($hit === null || ($hit['key'] === 'hl') !== $wantHl || isset($seen[$norm])) {
                    continue;
                }
                if ($hasStatus && strtoupper(trim((string) $r->campaignStatus)) === 'ARCHIVED') {
                    continue;
                }
                $cid = trim((string) $r->campaign_id);
                if ($cid === '') {
                    continue;
                }
                $seen[$norm] = true;
                $out[] = ['campaign_id' => $cid, 'campaign_name' => trim((string) $r->campaignName), 'norm' => $norm];
            }
        }

        return $out;
    }
}
