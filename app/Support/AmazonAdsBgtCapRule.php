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
 * data, ad types with lifetime spend of $30 or less ignored)
 * the suggested budget (SBGT) of the parent's linked campaigns is capped at an editable $ amount
 * (default $1). A cap only lowers a budget; an SBGT of 0 (pause) stays 0. A cap of $0 sets SBGT to 0,
 * which the budget push / live sync treat as "pause the campaign".
 */
final class AmazonAdsBgtCapRule
{
    public const SETTINGS_CHANNEL = 'amazon_ads_bgt_cap_rule';

    public const DEFAULT_CAP = 1;

    public const MAX_CAP = 9999;

    /** Inv Dil% rule: campaigns whose Dil% is above this ... */
    public const DEFAULT_DIL_ABOVE = 50;

    /** ... are capped at this $ amount. */
    public const DEFAULT_DIL_CAP = 1;

    public const MAX_DIL_ABOVE = 100000;

    /** Reviews rule 1: rating below this ... */
    public const DEFAULT_REV_LOW_BELOW = 3;

    /** ... is capped at this $ amount (0 pauses). */
    public const DEFAULT_REV_LOW_CAP = 0;

    /** Reviews rule 2: rating from / to (from inclusive, to exclusive) ... */
    public const DEFAULT_REV_MID_FROM = 3;

    public const DEFAULT_REV_MID_TO = 4;

    /** ... is capped at this $ amount. */
    public const DEFAULT_REV_MID_CAP = 1;

    /** Price rule: price up to this ($) is capped at DEFAULT_PRICE_LOW_CAP ... */
    public const DEFAULT_PRICE_LOW_UPTO = 20;

    public const DEFAULT_PRICE_LOW_CAP = 1;

    /** ... above LOW_UPTO and up to this ($) at DEFAULT_PRICE_MID_CAP ... */
    public const DEFAULT_PRICE_MID_UPTO = 50;

    public const DEFAULT_PRICE_MID_CAP = 2;

    /** ... and above MID_UPTO at DEFAULT_PRICE_HIGH_CAP. */
    public const DEFAULT_PRICE_HIGH_CAP = 3;

    public const MAX_PRICE = 100000;

    private const TIER_CACHE_KEY = 'amazon_ads_bgt_cap_tiers_v2';

    private const COUNT_CACHE_KEY = 'amazon_ads_bgt_cap_counts_v5';

    /** missing-page ACOS tier => colour name used by this rule */
    private const COLOURS = ['mid' => 'yellow', 'high' => 'red'];

    /** @var array<string, array{key: string, colour: string}>|null */
    private static ?array $tierMemo = null;

    /** @var array{enabled: bool, caps: array<string, array<string, int>>, dil: array{above: float, cap: int}, rev_low: array{below: float, cap: int}, rev_mid: array{from: float, to: float, cap: int}}|null */
    private static ?array $ruleMemo = null;

    /**
     * @return array{enabled: bool, caps: array<string, array{yellow: int, red: int}>, dil: array{above: float, cap: int}, rev_low: array{below: float, cap: int}, rev_mid: array{from: float, to: float, cap: int}}
     */
    public static function defaults(): array
    {
        $caps = [];
        foreach (AmazonAdsAcosPauseRules::keys() as $key) {
            $caps[$key] = ['yellow' => self::DEFAULT_CAP, 'red' => self::DEFAULT_CAP];
        }

        return [
            'enabled' => false,
            'caps' => $caps,
            'dil' => ['above' => (float) self::DEFAULT_DIL_ABOVE, 'cap' => self::DEFAULT_DIL_CAP],
            'rev_low' => ['below' => (float) self::DEFAULT_REV_LOW_BELOW, 'cap' => self::DEFAULT_REV_LOW_CAP],
            'rev_mid' => [
                'from' => (float) self::DEFAULT_REV_MID_FROM,
                'to' => (float) self::DEFAULT_REV_MID_TO,
                'cap' => self::DEFAULT_REV_MID_CAP,
            ],
            'price' => self::defaultPrice(),
        ];
    }

    /**
     * @return array{low_upto: float, low_cap: int, mid_upto: float, mid_cap: int, high_cap: int}
     */
    private static function defaultPrice(): array
    {
        return [
            'low_upto' => (float) self::DEFAULT_PRICE_LOW_UPTO,
            'low_cap' => self::DEFAULT_PRICE_LOW_CAP,
            'mid_upto' => (float) self::DEFAULT_PRICE_MID_UPTO,
            'mid_cap' => self::DEFAULT_PRICE_MID_CAP,
            'high_cap' => self::DEFAULT_PRICE_HIGH_CAP,
        ];
    }

    /**
     * @return array{enabled: bool, caps: array<string, array{yellow: int, red: int}>, dil: array{above: float, cap: int}, rev_low: array{below: float, cap: int}, rev_mid: array{from: float, to: float, cap: int}}
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

        $dil = is_array($input['dil'] ?? null) ? $input['dil'] : [];
        $base['dil'] = [
            'above' => self::normalizeThreshold($dil['above'] ?? self::DEFAULT_DIL_ABOVE, 'Inv Dil%', self::MAX_DIL_ABOVE),
            'cap' => self::normalizeDollarCap($dil['cap'] ?? self::DEFAULT_DIL_CAP, 'Inv Dil% cap'),
        ];

        $low = is_array($input['rev_low'] ?? null) ? $input['rev_low'] : [];
        $base['rev_low'] = [
            'below' => self::normalizeThreshold($low['below'] ?? self::DEFAULT_REV_LOW_BELOW, 'Reviews (low)', 5),
            'cap' => self::normalizeDollarCap($low['cap'] ?? self::DEFAULT_REV_LOW_CAP, 'Reviews (low) cap'),
        ];

        $mid = is_array($input['rev_mid'] ?? null) ? $input['rev_mid'] : [];
        $from = self::normalizeThreshold($mid['from'] ?? self::DEFAULT_REV_MID_FROM, 'Reviews (mid) from', 5);
        $to = self::normalizeThreshold($mid['to'] ?? self::DEFAULT_REV_MID_TO, 'Reviews (mid) to', 5);
        if ($from >= $to) {
            throw new \InvalidArgumentException('Reviews (mid) From must be lower than To.');
        }
        $base['rev_mid'] = [
            'from' => $from,
            'to' => $to,
            'cap' => self::normalizeDollarCap($mid['cap'] ?? self::DEFAULT_REV_MID_CAP, 'Reviews (mid) cap'),
        ];

        $price = is_array($input['price'] ?? null) ? $input['price'] : [];
        $lowUpto = self::normalizeThreshold($price['low_upto'] ?? self::DEFAULT_PRICE_LOW_UPTO, 'Price (low) up to', self::MAX_PRICE);
        $midUpto = self::normalizeThreshold($price['mid_upto'] ?? self::DEFAULT_PRICE_MID_UPTO, 'Price (mid) up to', self::MAX_PRICE);
        if ($lowUpto >= $midUpto) {
            throw new \InvalidArgumentException('Price: the first limit must be lower than the second.');
        }
        $base['price'] = [
            'low_upto' => $lowUpto,
            'low_cap' => self::normalizeDollarCap($price['low_cap'] ?? self::DEFAULT_PRICE_LOW_CAP, 'Price (low) cap'),
            'mid_upto' => $midUpto,
            'mid_cap' => self::normalizeDollarCap($price['mid_cap'] ?? self::DEFAULT_PRICE_MID_CAP, 'Price (mid) cap'),
            'high_cap' => self::normalizeDollarCap($price['high_cap'] ?? self::DEFAULT_PRICE_HIGH_CAP, 'Price (high) cap'),
        ];

        return $base;
    }

    private static function normalizeThreshold(mixed $raw, string $label, float $max): float
    {
        if (! is_numeric($raw) || ! is_finite((float) $raw)) {
            throw new \InvalidArgumentException($label.' must be a number.');
        }
        $n = (float) $raw;
        if ($n < 0 || $n > $max) {
            throw new \InvalidArgumentException($label.' must be between 0 and '.rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.').'.');
        }

        return $n;
    }

    private static function normalizeDollarCap(mixed $raw, string $label): int
    {
        if (! is_numeric($raw) || ! is_finite((float) $raw)) {
            throw new \InvalidArgumentException($label.' must be a number.');
        }
        $n = (int) floor((float) $raw);
        if ($n < 0 || $n > self::MAX_CAP) {
            throw new \InvalidArgumentException($label.' must be a whole dollar amount from 0 to '.self::MAX_CAP.'.');
        }

        return $n;
    }

    /**
     * @return array{enabled: bool, caps: array<string, array{yellow: int, red: int}>, dil: array{above: float, cap: int}, rev_low: array{below: float, cap: int}, rev_mid: array{from: float, to: float, cap: int}}
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
     * Cap the SBGT of a campaign when the rule is ON and one of these hit:
     * - the campaign's ad type is yellow / red (KW / PT / HL slabs),
     * - its Inv Dil% is above the Dil rule's threshold,
     * - its review rating falls in a Reviews rule range.
     * Where several apply the lowest cap wins. Only lowers a positive budget.
     */
    public static function apply(?int $sbgt, ?string $campaignName, ?float $dil = null, ?float $rating = null, ?float $price = null): ?int
    {
        if ($sbgt === null || $sbgt <= 0 || ! self::enabled()) {
            return $sbgt;
        }
        $cap = self::lowestCap([self::capForCampaign($campaignName), self::dilCap($dil), self::reviewsCap($rating), self::priceCap($price)]);

        return ($cap !== null && $sbgt > $cap) ? $cap : $sbgt;
    }

    /**
     * Cap from the Price rule: up to $20 = $1, above $20 up to $50 = $2, above $50 = $3 (editable).
     * Unknown prices (none / 0 or less) are never capped.
     */
    public static function priceCap(?float $price): ?int
    {
        if ($price === null || ! is_finite($price) || $price <= 0) {
            return null;
        }
        $rule = self::resolvedRule()['price'];
        if ($price <= $rule['low_upto']) {
            return $rule['low_cap'];
        }

        return $price <= $rule['mid_upto'] ? $rule['mid_cap'] : $rule['high_cap'];
    }

    /** Price slab of a price: low, mid or high (null when unknown). */
    public static function priceSlab(?float $price): ?string
    {
        if ($price === null || ! is_finite($price) || $price <= 0) {
            return null;
        }
        $rule = self::resolvedRule()['price'];
        if ($price <= $rule['low_upto']) {
            return 'low';
        }

        return $price <= $rule['mid_upto'] ? 'mid' : 'high';
    }

    /**
     * Cap from the Inv Dil% rule: set when the campaign's Dil% is above the threshold.
     */
    public static function dilCap(?float $dil): ?int
    {
        if ($dil === null || ! is_finite($dil)) {
            return null;
        }
        $rule = self::resolvedRule()['dil'];

        return $dil > $rule['above'] ? $rule['cap'] : null;
    }

    /**
     * Cap from the two Reviews rules. Unknown ratings (none / 0) are never capped.
     * Rule 1: rating below X. Rule 2: rating from Y (inclusive) up to Z (exclusive).
     */
    public static function reviewsCap(?float $rating): ?int
    {
        if ($rating === null || ! is_finite($rating) || $rating <= 0) {
            return null;
        }

        return self::lowestCap([self::reviewsLowCap($rating), self::reviewsMidCap($rating)]);
    }

    /** Reviews rule 1: rating below the threshold. */
    public static function reviewsLowCap(?float $rating): ?int
    {
        if ($rating === null || ! is_finite($rating) || $rating <= 0) {
            return null;
        }
        $rule = self::resolvedRule()['rev_low'];

        return $rating < $rule['below'] ? $rule['cap'] : null;
    }

    /** Reviews rule 2: rating from (inclusive) to (exclusive). */
    public static function reviewsMidCap(?float $rating): ?int
    {
        if ($rating === null || ! is_finite($rating) || $rating <= 0) {
            return null;
        }
        $rule = self::resolvedRule()['rev_mid'];

        return ($rating >= $rule['from'] && $rating < $rule['to']) ? $rule['cap'] : null;
    }

    /**
     * @param  list<int|null>  $caps
     */
    private static function lowestCap(array $caps): ?int
    {
        $caps = array_values(array_filter($caps, static fn ($c): bool => $c !== null));

        return $caps === [] ? null : min($caps);
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
     * @return array{total: int, counts: array<string, array{yellow: int, red: int}>, matched: array<string, array{yellow: int, red: int}>, dil: array{capped: int, matched: int}, rev_low: array{capped: int, matched: int}, rev_mid: array{capped: int, matched: int}}
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
        $out = ['total' => 0, 'counts' => $zero, 'matched' => $zero, 'dil' => ['capped' => 0, 'matched' => 0], 'rev_low' => ['capped' => 0, 'matched' => 0], 'rev_mid' => ['capped' => 0, 'matched' => 0], 'price_low' => ['capped' => 0, 'matched' => 0], 'price_mid' => ['capped' => 0, 'matched' => 0], 'price_high' => ['capped' => 0, 'matched' => 0]];

        try {
            $map = self::tierMap();
            foreach (['sp', 'sb'] as $channel) {
                // Every campaign: the Dil% rule is not limited to yellow / red ad types.
                $campaigns = self::latestCampaignsByName($channel, null);
                foreach (array_chunk($campaigns, 500) as $chunk) {
                    $dilByCid = [];
                    $ratingByCid = [];
                    $priceByCid = [];
                    $uncapped = AmazonAdsDesiredSbgtResolver::sbgtForCampaigns(
                        array_map(static fn (array $c): array => [
                            'campaign_id' => $c['campaign_id'],
                            'campaignName' => $c['campaign_name'],
                        ], $chunk),
                        $channel,
                        null,
                        null,
                        false,
                        $dilByCid,
                        $ratingByCid,
                        $priceByCid
                    );
                    foreach ($chunk as $c) {
                        $hit = $map[$c['norm']] ?? null;
                        $sbgt = $uncapped[$c['campaign_id']] ?? null;
                        $tierCap = null;
                        if ($hit !== null) {
                            $out['matched'][$hit['key']][$hit['colour']]++;
                            $tierCap = $rule['caps'][$hit['key']][$hit['colour']] ?? null;
                            if ($sbgt !== null && $tierCap !== null && $sbgt > $tierCap) {
                                $out['counts'][$hit['key']][$hit['colour']]++;
                            }
                        }
                        $dilCap = self::dilCap($dilByCid[$c['campaign_id']] ?? null);
                        if ($dilCap !== null) {
                            $out['dil']['matched']++;
                            if ($sbgt !== null && $sbgt > $dilCap) {
                                $out['dil']['capped']++;
                            }
                        }
                        $rating = $ratingByCid[$c['campaign_id']] ?? null;
                        $revCaps = [];
                        foreach (['rev_low' => self::reviewsLowCap($rating), 'rev_mid' => self::reviewsMidCap($rating)] as $revKey => $revCap) {
                            if ($revCap === null) {
                                continue;
                            }
                            $revCaps[] = $revCap;
                            $out[$revKey]['matched']++;
                            if ($sbgt !== null && $sbgt > $revCap) {
                                $out[$revKey]['capped']++;
                            }
                        }
                        $price = $priceByCid[$c['campaign_id']] ?? null;
                        $priceCap = self::priceCap($price);
                        $priceSlab = self::priceSlab($price);
                        if ($priceCap !== null && $priceSlab !== null) {
                            $out['price_'.$priceSlab]['matched']++;
                            if ($sbgt !== null && $sbgt > $priceCap) {
                                $out['price_'.$priceSlab]['capped']++;
                            }
                        }
                        $cap = self::lowestCap(array_merge([$tierCap, $dilCap, $priceCap], $revCaps));
                        if ($sbgt !== null && $cap !== null && $sbgt > $cap) {
                            $out['total']++;
                        }
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
     * @param  array<string, array{key: string, colour: string}>|null  $map  null = every campaign (no tier filter)
     * @return list<array{campaign_id: string, campaign_name: string, norm: string}>
     */
    private static function latestCampaignsByName(string $channel, ?array $map): array
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
                if ($norm === '' || isset($seen[$norm])) {
                    continue;
                }
                if ($map !== null) {
                    $hit = $map[$norm] ?? null;
                    if ($hit === null || ($hit['key'] === 'hl') !== $wantHl) {
                        continue;
                    }
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
