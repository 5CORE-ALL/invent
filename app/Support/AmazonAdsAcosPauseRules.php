<?php

namespace App\Support;

use App\Http\Controllers\AmazonAdsMissingController;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pause Rules 2 / 3 / 4: one rule per ad type (KW, PT, HL).
 *
 * A parent's KW / PT / HL campaigns are paused while that ad type's ACOS% is RED on
 * /amazon-ads/missing. "Red" is computed exactly like that page, always on LIFETIME data:
 *   - ACOS >= 100% (spend with no sales counts as 100%), or
 *   - the highest of the parent's KW / PT / HL ACOS values (needs 2+ different values).
 * An ad type with lifetime spend under $30 is ignored.
 * Rule 1 (Dil%) lives in {@see AmazonAdsPauseRule}.
 */
final class AmazonAdsAcosPauseRules
{
    /** Always lifetime data (the missing page's "Lifetime" period). */
    public const PERIOD = 'lt';

    /** Ad types with lifetime spend below this ($) are ignored. */
    public const MIN_SPEND = 30;

    /** @var array<string, array{n: int, label: string, channel: string}> */
    public const RULES = [
        'kw' => ['n' => 2, 'label' => 'KW', 'channel' => 'sp'],
        'pt' => ['n' => 3, 'label' => 'PT', 'channel' => 'sp'],
        'hl' => ['n' => 4, 'label' => 'HL', 'channel' => 'sb'],
    ];

    public static function keys(): array
    {
        return array_keys(self::RULES);
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return array_fill_keys(self::keys(), false);
    }

    /**
     * @return array<string, bool>
     */
    public static function normalize(mixed $input): array
    {
        $out = self::defaults();
        if (! is_array($input)) {
            return $out;
        }
        foreach (self::keys() as $key) {
            $v = $input[$key] ?? false;
            if (is_array($v)) {
                $v = $v['enabled'] ?? false;
            }
            $out[$key] = is_string($v)
                ? in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true)
                : (bool) $v;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $rule  resolved pause rule
     * @return list<string>
     */
    public static function enabledKeys(?array $rule): array
    {
        $flags = self::normalize($rule['acos_rules'] ?? null);

        return array_values(array_filter(self::keys(), static fn (string $k): bool => $flags[$k]));
    }

    public static function reason(string $key, float $acos): string
    {
        $def = self::RULES[$key];
        $shown = rtrim(rtrim(number_format($acos, 2, '.', ''), '0'), '.');

        return 'Rule '.$def['n'].' '.$def['label'].' ACOS% '.$shown.'% (red)';
    }

    /**
     * Which rule paused a campaign, from its stored pause reason: dil | kw | pt | hl | null.
     */
    public static function ruleKeyFromReason(mixed $reason): ?string
    {
        $r = trim((string) $reason);
        if (preg_match('/^Rule [234] (KW|PT|HL) ACOS%/i', $r, $m) === 1) {
            return strtolower($m[1]);
        }

        return AmazonAdsPauseRule::isDilPauseReason($r) ? 'dil' : null;
    }

    /**
     * Linked campaigns whose ad type has a red ACOS% on the missing-ads page.
     *
     * @param  list<string>  $keys  rule keys to evaluate (kw / pt / hl)
     * @return list<array{key: string, campaign_id: ?string, campaign_name: string, acos: float}>
     */
    public static function redCampaigns(array $keys, string $period = self::PERIOD): array
    {
        $out = [];
        foreach (self::tierCampaigns($keys, $period, ['high']) as $hit) {
            unset($hit['tier']);
            $out[] = $hit;
        }

        return $out;
    }

    /**
     * Count data per rule: campaigns that are red right now (lifetime, spend >= $30) and how many of
     * those are still running (not yet paused).
     *
     * @return array<string, array{red: int, active: int}>
     */
    public static function matchCounts(bool $fresh = false): array
    {
        $cacheKey = 'amazon_ads_acos_rule_match_counts_v1';
        if (! $fresh) {
            try {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            } catch (\Throwable) {
            }
        }
        $out = [];
        foreach (self::keys() as $k) {
            $out[$k] = ['red' => 0, 'active' => 0];
        }
        foreach (self::tierCampaigns(self::keys(), self::PERIOD, ['high']) as $hit) {
            $out[$hit['key']]['red']++;
            if ($hit['status'] === 'ENABLED') {
                $out[$hit['key']]['active']++;
            }
        }
        try {
            Cache::put($cacheKey, $out, now()->addMinutes(10));
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Linked campaigns by their ACOS colour on the missing-ads page: high = red, mid = yellow.
     *
     * @param  list<string>  $keys  kw / pt / hl
     * @param  list<string>  $tiers  high and / or mid
     * @return list<array{key: string, tier: string, campaign_id: ?string, campaign_name: string, status: string, acos: float, spend: float}>
     */
    public static function tierCampaigns(array $keys, string $period = self::PERIOD, array $tiers = ['high', 'mid']): array
    {
        $keys = array_values(array_intersect($keys, self::keys()));
        if ($keys === []) {
            return [];
        }
        try {
            $rows = (new AmazonAdsMissingController)->data()->getData(true)['data'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('Amazon Ads ACOS pause rules: could not load missing-ads data', ['error' => $e->getMessage()]);

            return [];
        }

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $acos = [];
            $spend = [];
            foreach (self::keys() as $k) {
                $acos[$k] = self::acosFor($row, $k, $period);
                $spend[$k] = (float) ($row[$k.'_m'][$period]['spend'] ?? 0);
            }
            foreach ($keys as $k) {
                // Colours are compared like the page; an ad type under the spend floor is ignored.
                if ($spend[$k] < self::MIN_SPEND) {
                    continue;
                }
                $tier = self::tier($acos, $k);
                if ($tier === null || ! in_array($tier, $tiers, true)) {
                    continue;
                }
                foreach ((array) ($row[$k] ?? []) as $link) {
                    $name = trim((string) ($link['campaign_name'] ?? ''));
                    $norm = AmazonAdsMissingLinks::normalizeCampaignName($name);
                    if ($norm === '' || isset($seen[$k.'|'.$norm])) {
                        continue;
                    }
                    $seen[$k.'|'.$norm] = true;
                    $out[] = [
                        'key' => $k,
                        'tier' => $tier,
                        'campaign_id' => preg_replace('/\D+/', '', (string) ($link['campaign_id'] ?? '')) ?: null,
                        'campaign_name' => $name,
                        'status' => strtoupper(trim((string) ($link['status'] ?? ''))),
                        'acos' => (float) $acos[$k],
                        'spend' => $spend[$k],
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Same as amzMetricValue(row, kind, 'acos') on the missing-ads page.
     *
     * @param  array<string, mixed>  $row
     */
    private static function acosFor(array $row, string $kind, string $period): ?float
    {
        $m = $row[$kind.'_m'][$period] ?? null;
        if (! is_array($m) || empty($row[$kind])) {
            return null;
        }
        $spend = (float) ($m['spend'] ?? 0);
        $sold = (float) ($m['sold'] ?? 0);
        $sales = (float) ($m['sales'] ?? 0);
        if ($sold == 0.0 && $spend > 0) {
            return 100.0;
        }

        return $sales > 0 ? ($spend / $sales) * 100 : null;
    }

    /**
     * Same as amzAcosTier(row, kind) on the missing-ads page: low (green) | mid (yellow) | high (red) | null.
     *
     * @param  array<string, ?float>  $acos
     */
    private static function tier(array $acos, string $kind): ?string
    {
        $mine = $acos[$kind] ?? null;
        if ($mine === null) {
            return null;
        }
        if ($mine >= 100) {
            return 'high';
        }
        $vals = array_values(array_filter($acos, static fn ($v): bool => $v !== null));
        if (count($vals) < 2) {
            return null;
        }
        $lo = min($vals);
        $hi = max($vals);
        if ($lo == $hi) {
            return null;
        }
        if ($mine == $lo) {
            return 'low';
        }
        if ($mine == $hi) {
            return 'high';
        }

        return 'mid';
    }
}
