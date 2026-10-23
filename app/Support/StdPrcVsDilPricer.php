<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S PRC from a channel's saved Std prc vs dil slabs.
 * S PRC = Std Prc × (1 − (Age + Dil + CVR up/down + Review + Buss + 0 Sold) / 100).
 * Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 * Clearance SKUs (inv days clearance = YES) keep the discount price.
 * Used by the unattended apply commands. The page does not have to be open.
 */
class StdPrcVsDilPricer
{
    /** Std Prc strictly below this uses {@see self::LOW_STD_FACTOR}. */
    public const LOW_STD_UNDER = 15.0;

    public const LOW_STD_FACTOR = 0.5;

    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<string, true>|null */
    private static ?array $clearanceKeys = null;

    /** @param array{dil:list<array<string,float>>,age:list<array<string,float>>,cvr:array<string,float>,reviews:list<array<string,float>>,review_max:int,buss?:list<array<string,float>>,zero_sold_disc?:float} $rules */
    public function __construct(private array $rules, private string $channel = '') {}

    /** Channels with no session CVR. CVR Disc is always 0 (Amazon A L30 / Sess30 is not used). */
    public const SKIP_CVR = ['bestbuy'];

    public static function skipsCvr(string $channel): bool
    {
        return in_array($channel, self::SKIP_CVR, true);
    }

    /** Drop the per-process rules cache, so rules saved a moment ago are read again. */
    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function forChannel(string $channel): self
    {
        if (isset(self::$cache[$channel])) {
            return self::$cache[$channel];
        }
        $defaults = self::defaults();
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel.'_std_prc_vs_dil')
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : [];
        $rules = [
            'dil' => self::ranges($saved['dil'] ?? null, $defaults['dil']),
            'age' => self::ranges($saved['age'] ?? null, $defaults['age']),
            'cvr' => self::cvr($saved['cvr'] ?? null, $defaults['cvr']),
            'reviews' => self::ranges($saved['reviews'] ?? null, $defaults['reviews']),
            'review_max' => is_numeric($saved['review_max'] ?? null) ? max(1, (int) $saved['review_max']) : $defaults['review_max'],
            'buss' => self::ranges($saved['buss'] ?? null, $defaults['buss']),
            'pricing' => self::ranges($saved['pricing'] ?? null, $defaults['pricing']),
            'roi' => self::roiRules($saved['roi'] ?? null),
            'lmp' => self::ranges($saved['lmp'] ?? null, $defaults['lmp']),
            'moq_m' => self::ranges($saved['moq_m'] ?? null, $defaults['moq_m']),
            'snroi_cap' => self::normalizeSnroiCapRules($saved['snroi_cap'] ?? null),
            'zero_sold_disc' => self::discPercent($saved['zero_sold_disc'] ?? null),
        ];

        return self::$cache[$channel] = new self($rules, $channel);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function priceFromRow(array $row): ?float
    {
        $metric = self::usesOwnChannelMetrics($this->channel) ? null : $this->lookupAmazonMetric($row);
        $inv = $metric !== null ? $metric['inv'] : (float) ($row['inv'] ?? $row['INV'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        $std = (float) ($row['std_price'] ?? $row['std'] ?? $row['STANDARD_PRICE'] ?? $row['standard_price'] ?? 0);
        if (! ($std > 0)) {
            return null;
        }
        $dil = $metric !== null ? $metric['dil'] : (float) ($row['dil'] ?? 0);
        $age = is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : $this->lookupAgeDays($row, $inv);
        $cvr = $metric !== null ? $metric['cvr'] : (float) ($row['cvr'] ?? 0);
        $cvr60 = $metric !== null ? $metric['cvr45'] : (float) ($row['cvr_60'] ?? 0);
        $reviews = (float) ($row['review_count'] ?? $row['reviews'] ?? $row['Reviews'] ?? 0);
        $ageDisc = self::scaleRuleDisc($this->ageDisc($age), $std);
        $dilDisc = self::scaleRuleDisc($this->rangeDisc($dil, $this->rules['dil']), $std);
        $trend = in_array($row['cvr_trend'] ?? null, ['down', 'up', 'flat'], true) ? $row['cvr_trend'] : null;
        // Best Buy has no session CVR. A missing CVR is "down" in cvrDisc() and would take
        // Amazon's 6% slab. Skip the slab instead of reading Amazon A L30 / Sess30.
        $cvrDisc = self::skipsCvr($this->channel)
            ? 0.0
            : self::scaleRuleDisc($this->cvrDisc($cvr, $cvr60, $trend), $std);
        $reviewDisc = self::scaleRuleDisc($this->reviewDisc($reviews), $std);
        $bussDisc = $this->rangeDisc($std, $this->rules['buss'] ?? []);
        // Pricing Disc: Std Price slabs, full Disc % (never halved under $15), added to the sum.
        $pricingDisc = $this->rangeDisc($std, $this->rules['pricing'] ?? []);
        // ROI Disc: GROI% slab that also matches this SKU's Dil slab. Only rows that carry the listing price get one.
        $roi = $this->roiPct($row);
        $roiDisc = $roi === null
            ? 0.0
            : self::scaleRuleDisc($this->roiRuleDisc($roi, $dil), $std);
        $zeroSoldDisc = $this->isZeroSold($row)
            ? self::scaleRuleDisc((float) ($this->rules['zero_sold_disc'] ?? 0), $std)
            : 0.0;
        $lmp = (float) ($row['lmp_raw'] ?? 0);
        if (! ($lmp > 0)) {
            $lmp = (float) ($row['lmp'] ?? $row['lmp_price'] ?? 0);
        }
        $lmpDisc = $lmp > 0
            ? self::scaleRuleDisc($this->rangeDisc($dil, $this->rules['lmp'] ?? []), $std)
            : 0.0;
        $moq = is_numeric($row['moq'] ?? null) ? (float) $row['moq'] : 0.0;
        $ovL30 = (float) ($row['ov_l30'] ?? $row['L30'] ?? 0);
        $moqMDisc = 0.0;
        if ($moq > 0) {
            $dilMoq = (float) round(($ovL30 / ($moq / 4)) * 100);
            $moqMDisc = self::scaleRuleDisc($this->rangeDisc($dilMoq, $this->rules['moq_m'] ?? []), $std);
        }
        $sum = min(99.99, max(-100, $ageDisc + $dilDisc + $cvrDisc + $reviewDisc + $bussDisc + $pricingDisc + $roiDisc + $zeroSoldDisc + $lmpDisc + $moqMDisc));
        $price = round($std * (1 - $sum / 100), 2);
        if (! ($price > 0)) {
            return null;
        }
        // Channels whose S PRC is "calc − Ship" (TopDawg): take Ship off BEFORE the SNROI cap, so the cap
        // judges the S PRC that is really saved and pushed.
        $shipOff = is_numeric($row['ship_off'] ?? null) ? (float) $row['ship_off'] : 0.0;
        if ($shipOff > 0) {
            $price = round($price - $shipOff, 2);
            if (! ($price > 0)) {
                return null;
            }
        }
        $price = $this->applySnroiCap($price, $dil, $row, $std - $shipOff);

        return $price > 0 ? $price : null;
    }

    /**
     * SNROI Cap: when the SKU's Dil slab has a Target SNROI %, S PRC is the price that lands on that target. The
     * Age / Dil / B / 0 Sold / CVR / Review discounts are used only when the slab has no cap. Std Prc stays the
     * maximum ($maxPrice), so a target that needs more than Std holds at Std.
     *
     * @param  array<string, mixed>  $row
     */
    private function applySnroiCap(float $price, float $dil, array $row, float $maxPrice = 0.0): float
    {
        // A caller that solves the cap on its own price chain (New Temu Two) asks for it to be skipped here.
        if (! empty($row['skip_snroi_cap'])) {
            return $price;
        }
        $target = $this->snroiCapFor($dil);
        if ($target === null) {
            return $price;
        }
        $lp = (float) ($row['lp'] ?? $row['LP'] ?? $row['LP_productmaster'] ?? 0);
        // A row that does not carry the take-home % / Ads % (a channel job that forgot to send them) takes the
        // channel's own values, the ones its SNROI% column and the negative-SNROI check use. Channels without Ship
        // keep the plain defaults: they are left exactly as they were.
        $fallback = $this->usesChannelSnroiInputs();
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1) && $fallback) {
            $margin = $this->channelSnroiMargin();
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.80;
        }
        // Temu 3: the S PRC is the full Temu price and only S PRC × 0.88 (S Recovery) is earned, so the SNROI% column
        // measures (S PRC × 0.88 × take-home − Ship − LP) ÷ LP.
        $margin *= self::snroiRecoveryRate($this->channel);
        $ship = self::excludesShip($this->channel) ? 0.0 : (float) ($row['ship'] ?? 0);
        // Ads % as a fraction of the price (the auto-push rows pass 0.05 for 5%). Pages without ads pass 0.
        if (is_numeric($row['ads'] ?? null)) {
            $ads = (float) $row['ads'];
        } else {
            $ads = $fallback ? $this->channelSnroiAdsFrac() : 0.0;
        }
        if ($ads > 1) {
            $ads /= 100;
        }
        if ($ads < 0 || $ads >= $margin) {
            $ads = 0.0;
        }

        $solved = self::snroiTargetPrice($target, $lp, $margin, $ship, $ads);
        if ($solved === null) {
            return $price;
        }
        if ($maxPrice > 0 && $solved > $maxPrice) {
            $solved = round($maxPrice, 2);
        }

        return $solved > 0 ? $solved : $price;
    }

    /**
     * The price whose SNROI is the target: (LP × (1 + target%) + ship) ÷ (margin − ads), rounded down to the cent.
     * Null when LP or the net margin is missing, so the caller keeps its discount price.
     */
    public static function snroiTargetPrice(float $target, float $lp, float $margin, float $ship = 0.0, float $ads = 0.0): ?float
    {
        $net = $margin - $ads;
        if (! ($lp > 0) || ! ($net > 0)) {
            return null;
        }
        $price = floor(((($lp * (1 + $target / 100)) + $ship) / $net) * 100 + 0.00001) / 100;

        return $price > 0 ? $price : null;
    }

    /**
     * Pages with no Ship (and Amazon, which has its own job) keep their own inputs: never use the channel fallback.
     * eBay 1 / 2 / 3 send take-home %, Ship and Ads% on every row, and fall back to the channel's own values.
     */
    private function usesChannelSnroiInputs(): bool
    {
        $c = strtolower(trim($this->channel));
        if ($c === '' || $c === 'amazon' || self::excludesShip($c)) {
            return false;
        }

        return ! in_array($c, ['doba_withoutship', 'fb_marketplace', 'instagram'], true);
    }

    private function channelSnroiMargin(): float
    {
        try {
            return (float) NegativeSnroiPushGuard::snroiMarginFor($this->channel);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function channelSnroiAdsFrac(): float
    {
        // Only these pages take Ads % off in their SNROI% column. Every other page's column has no Ads %.
        if (! in_array(strtolower(trim($this->channel)), ['ebay1', 'ebay2', 'ebay3', 'reverb', 'shopify_b2c'], true)) {
            return 0.0;
        }
        try {
            $pct = (float) NegativeSnroiPushGuard::snroiAdsPctFor($this->channel);
        } catch (\Throwable) {
            return 0.0;
        }

        return ($pct > 0 && $pct < 100) ? $pct / 100 : 0.0;
    }

    /** Target SNROI % of the saved SNROI Cap slab for this Dil %. Null when the slab has no cap or no slab matches. */
    public function snroiCapTargetForDil(float $dil): ?float
    {
        return $this->snroiCapFor($dil);
    }

    /** Target SNROI % of the Dil slab this value falls in. Null when the slab has no cap or no slab matches. */
    private function snroiCapFor(float $dil): ?float
    {
        if (! self::supportsSnroiCap($this->channel)) {
            return null;
        }

        return self::snroiCapTarget($dil, $this->rules['snroi_cap'] ?? []);
    }

    /**
     * Target SNROI % of the Dil slab a value falls in. Null when the slab has no cap or no slab matches.
     * Shared with the Amazon auto-push, which keeps its own rules store.
     *
     * @param  list<array{min:float,max:float,cap:float|null}>  $rules
     */
    public static function snroiCapTarget(float $dil, array $rules): ?float
    {
        if ($rules === [] || $dil < 0) {
            return null;
        }
        $last = count($rules) - 1;
        foreach ($rules as $i => $rule) {
            $min = (float) $rule['min'];
            $max = (float) $rule['max'];
            $hit = abs($max - $min) < 0.00001
                ? abs($dil - $min) < 0.00001
                : ($dil >= $min && ($i === $last ? $dil <= $max : $dil < $max));
            if ($hit) {
                return $rule['cap'] === null ? null : (float) $rule['cap'];
            }
        }

        return null;
    }

    /**
     * SNROI at a price: ((price × (margin − ads)) − ship − LP) ÷ LP × 100.
     * Returns the price unchanged when it is already at or under the target. Otherwise the price that gives the
     * target, rounded DOWN to the cent so the SNROI never ends up above it.
     */
    public static function capPriceToSnroi(float $price, float $target, float $lp, float $margin, float $ship = 0.0, float $ads = 0.0): float
    {
        $net = $margin - $ads;
        if (! ($price > 0) || ! ($lp > 0) || ! ($net > 0)) {
            return $price;
        }
        $snroi = (($price * $net) - $ship - $lp) / $lp * 100;
        if ($snroi <= $target + 0.00001) {
            return $price;
        }
        $capped = floor((($lp * (1 + $target / 100) + $ship) / $net) * 100 + 0.00001) / 100;
        if (! ($capped > 0) || $capped >= $price) {
            return $price;
        }

        return $capped;
    }

    /**
     * One row per Dil % slab: min, max and a Target SNROI % cap (null = no cap for that slab).
     *
     * @param  mixed  $incoming
     * @return list<array{min:float,max:float,cap:float|null}>
     */
    public static function normalizeSnroiCapRules($incoming): array
    {
        if (! is_array($incoming)) {
            return [];
        }
        $rules = [];
        foreach ($incoming as $item) {
            if (! is_array($item) || ! is_numeric($item['min'] ?? null) || ! is_numeric($item['max'] ?? null)) {
                continue;
            }
            $min = (float) $item['min'];
            $max = (float) $item['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $cap = is_numeric($item['cap'] ?? null) ? round(min(9999, max(-100, (float) $item['cap'])), 2) : null;
            $rules[] = ['min' => round($min, 2), 'max' => round($max, 2), 'cap' => $cap];
        }

        return $rules;
    }

    /**
     * Inv days clearance = YES (row flag, or the SKU is in inv_days_clearances).
     *
     * @param  array<string, mixed>  $row
     */
    public function isClearanceRow(array $row): bool
    {
        return $this->rowIsClearance($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsClearance(array $row): bool
    {
        if (array_key_exists('clearance', $row)) {
            $value = $row['clearance'];
            if (is_bool($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value === 1;
            }
            $flag = strtoupper(trim((string) $value));

            return in_array($flag, ['YES', 'Y', 'TRUE'], true);
        }
        $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
        if ($sku === '') {
            return false;
        }

        return isset($this->clearanceYesKeys()[self::clearanceKey($sku)]);
    }

    /**
     * @return array<string, true>
     */
    private function clearanceYesKeys(): array
    {
        if (self::$clearanceKeys !== null) {
            return self::$clearanceKeys;
        }
        if (! function_exists('app')) {
            return [];
        }
        try {
            if (! app()->bound('db') || ! Schema::hasTable('inv_days_clearances')) {
                return self::$clearanceKeys = [];
            }
            $set = [];
            foreach (DB::table('inv_days_clearances')->where('value', 'YES')->get(['sku', 'sku_key']) as $hit) {
                foreach ([(string) ($hit->sku ?? ''), (string) ($hit->sku_key ?? '')] as $raw) {
                    $key = self::clearanceKey($raw);
                    if ($key !== '') {
                        $set[$key] = true;
                    }
                }
            }

            return self::$clearanceKeys = $set;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function clearanceKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return strtolower($sku);
    }

    /** Std Prc under $15 → rule discount × 0.5. $15 and above keep the saved discount. */
    public static function scaleRuleDisc(float $disc, float $std): float
    {
        if (! ($std > 0) || $std >= self::LOW_STD_UNDER) {
            return $disc;
        }

        return round($disc * self::LOW_STD_FACTOR, 2);
    }

    /**
     * Amazon analytics sends age_days on the row. Other apply jobs do not,
     * so use the same Shopify push clock when the row omitted it.
     *
     * @param  array<string, mixed>  $row
     */
    private function lookupAgeDays(array $row, float $inv): ?float
    {
        $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
        if ($sku === '' || ! function_exists('app')) {
            return null;
        }
        try {
            if (! app()->bound('db')) {
                return null;
            }
            $days = app(\App\Http\Controllers\ProductMaster\InvDaysController::class)->ageDaysFor($sku, $inv);
        } catch (\Throwable $e) {
            return null;
        }

        return is_int($days) ? (float) $days : null;
    }

    /**
     * Amazon Dil % and CVR L30/L45. Same inputs as /amazon-tabulator-view.
     *
     * @param  array<string, mixed>  $row
     * @return array{dil:float,cvr:float,cvr45:float,inv:float}|null
     */
    private function lookupAmazonMetric(array $row): ?array
    {
        $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
        if ($sku === '' || ! function_exists('app')) {
            return null;
        }
        try {
            if (! app()->bound('db')) {
                return null;
            }
            $hit = app(\App\Http\Controllers\ProductMaster\InvDaysController::class)->amazonStdMetricFor($sku);
        } catch (\Throwable $e) {
            return null;
        }
        if (! is_array($hit)) {
            return null;
        }
        $s30 = (float) $hit['s30'];
        $s60 = (float) $hit['s60'];
        $a30 = (float) $hit['a30'];
        $a60 = (float) $hit['a60'];
        $inv = (float) $hit['inv'];
        $sess45 = ($s30 + $s60) / 2;

        return [
            'inv' => $inv,
            'dil' => $inv > 0 ? ((float) $hit['ov'] / $inv) * 100 : 0.0,
            'cvr' => $s30 > 0 ? ($a30 / $s30) * 100 : 0.0,
            'cvr45' => $sess45 > 0 ? ((($a30 + $a60) / 2) / $sess45) * 100 : 0.0,
        ];
    }

    private function ageDisc(?float $age): float
    {
        if ($age === null) {
            return 0.0;
        }

        return $this->rangeDisc($age, $this->rules['age']);
    }

    /**
     * Pages that have the SNROI Cap rule: Amazon, AliExpress, TikTok 1 / 2, eBay 1 / 2 / 3 and Temu 1 / 2 / 3 (same rule chain as Amazon, SNROI%
     * judged with each page's own take-home %, Ship and Ads%) and the pages with no Ship (Wayfair, Faire, Topdawg,
     * Alibaba, Shopify B2B, Depop, Mercari w/o ship, Doba w/o ship, FB Marketplace, Instagram). Every other page prices
     * from its discounts only.
     * New Temu One / Two (newtemuone, newtemutwo) solve the cap on their own Price After Discount chain.
     */
    public static function supportsSnroiCap(string $channel): bool
    {
        $c = strtolower(trim($channel));

        return $c === 'amazon'
            || $c === 'aliexpress'
            || in_array($c, ['tiktok', 'tiktok2'], true)
            || self::usesOwnChannelMetrics($c)
            || in_array($c, ['newtemuone', 'newtemutwo', 'temu3'], true)
            || self::excludesShip($c)
            || in_array($c, ['doba_withoutship', 'fb_marketplace', 'instagram'], true);
    }

    /** Share of S PRC the page's SNROI% column earns. Temu 3 uses S Recovery (S PRC × 0.88). Every other page is 1. */
    public static function snroiRecoveryRate(string $channel): float
    {
        return strtolower(trim($channel)) === 'temu3' ? 0.88 : 1.0;
    }

    public static function excludesShip(string $channel): bool
    {
        return in_array($channel, [
            'shopify_b2b',
            'faire',
            'wayfair',
            'topdawg',
            'alibaba',
            'depop',
            'mercari_woship',
        ], true);
    }

    /**
     * @param  list<array<string, float>>  $rules
     */
    private function rangeDisc(float $value, array $rules, bool $allowNegative = false): float
    {
        if ($rules === [] || (! $allowNegative && $value < 0)) {
            return 0.0;
        }
        $last = count($rules) - 1;
        foreach ($rules as $i => $rule) {
            $min = (float) $rule['min'];
            $max = (float) $rule['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $hit = abs($max - $min) < 0.00001
                ? abs($value - $min) < 0.00001
                : ($value >= $min && ($i === $last ? $value <= $max : $value < $max));
            if ($hit) {
                return (float) $rule['disc'];
            }
        }

        return 0.0;
    }

    /**
     * eBay 1 / 2 / 3 price from their own row (INV, Dil, CVR, sold qty), never from Amazon sessions or A L30.
     */
    public static function usesOwnChannelMetrics(string $channel): bool
    {
        return in_array($channel, ['ebay1', 'ebay2', 'ebay3'], true);
    }

    private function cvrDisc(float $cvr, float $cvr60, ?string $trend = null): float
    {
        $cfg = $this->rules['cvr'];
        if ($trend === null) {
            $trend = 'flat';
            // Same as Amazon: CVR 0 (no sessions) is Down, then the down slabs.
            if ($cvr <= 0.0 || ($cvr60 > 0 && $cvr < $cvr60 - 0.1)) {
                $trend = 'down';
            } elseif ($cvr60 > 0 && $cvr > $cvr60 + 0.1) {
                $trend = 'up';
            }
        }
        if ($trend === 'down') {
            $slabs = [];
            if (($cfg['down2_lt'] ?? 0) > 0) {
                $slabs[] = ['lt' => (float) $cfg['down2_lt'], 'disc' => (float) $cfg['down2_disc']];
            }
            if (($cfg['down_lt'] ?? 0) > 0) {
                $slabs[] = ['lt' => (float) $cfg['down_lt'], 'disc' => (float) $cfg['down_disc']];
            }
            usort($slabs, fn ($a, $b) => $a['lt'] <=> $b['lt']);
            foreach ($slabs as $slab) {
                if ($cvr < $slab['lt']) {
                    return (float) $slab['disc'];
                }
            }
        }
        if ($trend === 'up') {
            $slabs = [
                ['gt' => (float) ($cfg['up_gt'] ?? 0), 'disc' => (float) ($cfg['up_disc'] ?? 0)],
            ];
            if (is_numeric($cfg['up2_gt'] ?? null)) {
                $slabs[] = ['gt' => (float) $cfg['up2_gt'], 'disc' => (float) ($cfg['up2_disc'] ?? 0)];
            }
            usort($slabs, fn ($a, $b) => $b['gt'] <=> $a['gt']);
            foreach ($slabs as $slab) {
                if ($cvr > $slab['gt']) {
                    return (float) $slab['disc'];
                }
            }
        }

        return (float) ($cfg['flat_disc'] ?? 0);
    }

    private function reviewDisc(float $reviews): float
    {
        $max = (int) $this->rules['review_max'];
        if (! ($reviews > 0) || $reviews >= $max) {
            return 0.0;
        }
        foreach ($this->rules['reviews'] as $rule) {
            if ($reviews >= (float) $rule['min'] && $reviews <= (float) $rule['max']) {
                return (float) $rule['disc'];
            }
        }

        return 0.0;
    }

    /**
     * @param  mixed  $incoming
     * @param  list<array<string, float>>  $fallback
     * @return list<array<string, float>>
     */
    private static function ranges($incoming, array $fallback): array
    {
        if (! is_array($incoming)) {
            return $fallback;
        }
        $rules = [];
        foreach ($incoming as $item) {
            if (! is_array($item) || ! is_numeric($item['min'] ?? null) || ! is_numeric($item['max'] ?? null)) {
                continue;
            }
            $min = (float) $item['min'];
            $max = (float) $item['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $disc = is_numeric($item['disc'] ?? null) ? (float) $item['disc'] : 0.0;
            $rules[] = ['min' => $min, 'max' => $max, 'disc' => min(100, max(-100, $disc))];
        }

        return $rules !== [] ? $rules : $fallback;
    }

    /**
     * @param  mixed  $incoming
     * @param  array<string, float>  $defaults
     * @return array<string, float>
     */
    private static function cvr($incoming, array $defaults): array
    {
        $out = $defaults;
        if (! is_array($incoming)) {
            return $out;
        }
        foreach (['down2_lt', 'down_lt', 'up_gt', 'up2_gt', 'down2_disc', 'down_disc', 'up_disc', 'up2_disc', 'flat_disc'] as $key) {
            if (! is_numeric($incoming[$key] ?? null)) {
                continue;
            }
            $n = (float) $incoming[$key];
            // Thresholds stay >= 0. Discounts may be negative (they offset the other discounts).
            if (str_ends_with($key, '_disc')) {
                $out[$key] = min(100, max(-100, $n));
            } elseif ($n >= 0) {
                $out[$key] = $n;
            }
        }
        if (! is_numeric($incoming['down2_disc'] ?? null)) {
            $out['down2_disc'] = $out['down_disc'];
        }
        if (! is_numeric($incoming['up2_disc'] ?? null)) {
            $out['up2_disc'] = $out['up_disc'];
        }

        return $out;
    }

    /**
     * @return array{dil:list<array<string,float>>,age:list<array<string,float>>,cvr:array<string,float>,reviews:list<array<string,float>>,review_max:int,buss:list<array<string,float>>}
     */
    private static function defaults(): array
    {
        return [
            'dil' => [
                ['min' => 0, 'max' => 0, 'disc' => 0],
                ['min' => 0.1, 'max' => 10, 'disc' => 0],
                ['min' => 10, 'max' => 25, 'disc' => 0],
                ['min' => 25, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 100, 'disc' => 0],
                ['min' => 100, 'max' => 9999, 'disc' => 0],
            ],
            'age' => [
                ['min' => 0, 'max' => 30, 'disc' => 0],
                ['min' => 31, 'max' => 60, 'disc' => 0],
                ['min' => 61, 'max' => 90, 'disc' => 0],
                ['min' => 91, 'max' => 180, 'disc' => 0],
                ['min' => 181, 'max' => 365, 'disc' => 0],
                ['min' => 366, 'max' => 9999, 'disc' => 0],
            ],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [
                ['min' => 1, 'max' => 2, 'disc' => 4],
                ['min' => 2, 'max' => 3, 'disc' => 4],
            ],
            'review_max' => 4,
            'buss' => [
                ['min' => 0, 'max' => 15, 'disc' => 0],
                ['min' => 15, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 9999, 'disc' => 0],
            ],
            'pricing' => [
                ['min' => 0, 'max' => 15, 'disc' => 0],
                ['min' => 15, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 9999, 'disc' => 0],
            ],
            'lmp' => [
                ['min' => 0, 'max' => 0, 'disc' => 0],
                ['min' => 0.1, 'max' => 10, 'disc' => 0],
                ['min' => 10, 'max' => 25, 'disc' => 0],
                ['min' => 25, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 100, 'disc' => 0],
                ['min' => 100, 'max' => 9999, 'disc' => 0],
            ],
            'moq_m' => [
                ['min' => 0, 'max' => 0, 'disc' => 0],
                ['min' => 0.1, 'max' => 25, 'disc' => 0],
                ['min' => 25, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 100, 'disc' => 0],
                ['min' => 100, 'max' => 200, 'disc' => 0],
                ['min' => 200, 'max' => 9999, 'disc' => 0],
            ],
            'zero_sold_disc' => 0,
        ];
    }

    /**
     * @param  mixed  $incoming
     * @return list<array{min:float,max:float,dil_min:float,dil_max:float,disc:float}>
     */
    private static function roiRules($incoming): array
    {
        if (! is_array($incoming)) {
            return [];
        }
        $rules = [];
        foreach ($incoming as $item) {
            if (! is_array($item) || ! is_numeric($item['min'] ?? null) || ! is_numeric($item['max'] ?? null)) {
                continue;
            }
            $min = (float) $item['min'];
            $max = (float) $item['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $dilMin = is_numeric($item['dil_min'] ?? null) ? (float) $item['dil_min'] : 0.0;
            $dilMax = is_numeric($item['dil_max'] ?? null) ? (float) $item['dil_max'] : 9999.0;
            if ($dilMax < $dilMin) {
                [$dilMin, $dilMax] = [$dilMax, $dilMin];
            }
            $rules[] = [
                'min' => $min,
                'max' => $max,
                'dil_min' => $dilMin,
                'dil_max' => $dilMax,
                'disc' => self::discPercent($item['disc'] ?? null),
            ];
        }

        return $rules;
    }

    /** Slab hit: [min, max), a point slab (min = max) is exact, and a 9999 upper bound includes the value. */
    private static function inSlab(float $value, float $min, float $max): bool
    {
        if (abs($max - $min) < 0.00001) {
            return abs($value - $min) < 0.00001;
        }

        return $value >= $min && ($value < $max || ($max >= 9999 && $value <= $max));
    }

    /** First ROI slab whose GROI% range and Dil range both match. */
    private function roiRuleDisc(float $roi, float $dil): float
    {
        foreach ($this->rules['roi'] ?? [] as $rule) {
            if (self::inSlab($roi, (float) $rule['min'], (float) $rule['max'])
                && self::inSlab($dil, (float) $rule['dil_min'], (float) $rule['dil_max'])) {
                return (float) $rule['disc'];
            }
        }

        return 0.0;
    }

    /**
     * GROI% at the current listing price: (price × margin − ship − lp) ÷ lp. Null when the row has no price or LP.
     *
     * @param  array<string, mixed>  $row
     */
    private function roiPct(array $row): ?float
    {
        foreach (['roi', 'groi', 'GROI%'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (float) $row[$key];
            }
        }
        $price = (float) ($row['live'] ?? $row['price'] ?? $row['Price'] ?? 0);
        $lp = (float) ($row['lp'] ?? $row['LP'] ?? $row['LP_productmaster'] ?? 0);
        if (! ($price > 0) || ! ($lp > 0)) {
            return null;
        }
        $margin = (float) ($row['margin'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0 && $margin <= 1)) {
            $margin = 0.80;
        }
        $ship = (float) ($row['ship'] ?? 0);

        return (($price * $margin - $ship - $lp) / $lp) * 100;
    }

    private static function discPercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(min(100, max(-100, (float) $value)), 2);
    }

    /**
     * Sold qty for this channel. Missing sold data does not treat the row as 0 sold.
     *
     * @param  array<string, mixed>  $row
     */
    private function isZeroSold(array $row): bool
    {
        $sold = $this->soldQty($row);
        if ($sold === null) {
            return false;
        }

        return ! ($sold > 0);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function soldQty(array $row): ?float
    {
        if ($this->channel === 'alibaba') {
            foreach (['al30', 'AB L30', 'AL30'] as $alt) {
                if (array_key_exists($alt, $row) && is_numeric($row[$alt])) {
                    return (float) $row[$alt];
                }
            }

            // OV L30 / Amazon A L30 are not Alibaba sales. Missing AB L30 is not 0 sold.
            return null;
        }
        $key = match ($this->channel) {
            'ebay1', 'ebay2', 'ebay3', 'ebay2op' => 'ebay_l30',
            'shopify_b2c' => 'b2c_l30',
            'macys', 'macy' => 'mc_l30',
            'purchasing_power' => 'pp_l30',
            'bestbuy' => 'bb_l30',
            'temu', 'temu2', 'temu3' => 'temu_l30',
            'aliexpress', 'shein', 'faire' => 'al30',
            'amazon' => 'a_l30',
            'tiktok', 'tiktok2' => 'tt_l30',
            'pls' => 'pls_l30',
            default => null,
        };
        if ($key !== null && array_key_exists($key, $row) && is_numeric($row[$key])) {
            return (float) $row[$key];
        }
        foreach (['sold', 'a_l30', 'A_L30', 'ebay_l30', 'b2c_l30', 'mc_l30', 'pp_l30', 'bb_l30'] as $fallback) {
            if (array_key_exists($fallback, $row) && is_numeric($row[$fallback])) {
                return (float) $row[$fallback];
            }
        }

        return null;
    }
}
