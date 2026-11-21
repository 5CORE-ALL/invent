<?php

namespace App\Support;

/**
 * Amazon CVR Price Rule — server twin of amzCvrRuleInfo() in partials/amazon-cvr-price-rule.blade.php.
 *
 * CVR L30 (A L30 / sessions L30) is compared with LT O CVR ((A L30 + units L60) / (sessions L30 + L60)): Down / within
 * target / Up, each with a price change %. The change is taken off the S PRC the other rules already produced (caps
 * done), NOT off the live Price, so the answer is the same before and after a push (a % off the live Price would
 * compound on every run). Lower cap: the new price never goes under the price where NPFT% or NROI% reaches its minimum,
 * solved with the real channel Ads %. Upper cap: the new price never goes over the price where NPFT% or NROI% reaches its maximum.
 */
class AmazonCvrPriceRule
{
    /**
     * @param  array<string, mixed>  $row  AmazonSprcDilAutoPushService::loadCandidates() row (a_l30, sess30, a_l60, sess60, lp, ship)
     * @param  array<string, mixed>|null  $cfg  OverallAmazonController::amazonCvrPriceRuleConfig()
     * @return array{price: float, capped: bool}|null  null = the rule does not change this SKU
     */
    public static function apply(float $base, array $row, ?array $cfg, float $channelAds): ?array
    {
        if ($cfg === null || empty($cfg['enabled']) || ! ($base > 0)) {
            return null;
        }
        $aL30 = (float) ($row['a_l30'] ?? 0);
        $sess30 = (float) ($row['sess30'] ?? $row['sessions_l30'] ?? 0);
        $aL60 = (float) ($row['a_l60'] ?? $row['units_ordered_l60'] ?? 0);
        $sess60 = (float) ($row['sess60'] ?? $row['sessions_l60'] ?? 0);
        $sess = $sess30 + $sess60;
        // No lifetime CVR (no sessions): it starts from LT O CVR = 0%.
        $lto = $sess <= 0 ? 0.0 : (($aL30 + $aL60) / $sess) * 100;
        $cvr = $sess30 == 0.0 ? 0.0 : ($aL30 / $sess30) * 100;
        $down = self::band($cfg['down_band'] ?? null, 20.0, 99.0) / 100;
        $up = self::band($cfg['up_band'] ?? null, 20.0, 1000.0) / 100;
        $pinkRaw = $cfg['pink'] ?? null;
        $pinkOn = $pinkRaw !== null && $pinkRaw !== '' && is_numeric($pinkRaw);
        $pinkAbove = (isset($cfg['pink_above']) && is_numeric($cfg['pink_above']) && (float) $cfg['pink_above'] >= 0)
            ? (float) $cfg['pink_above'] : 12.99;
        if ($pinkOn && $cvr > $pinkAbove) {
            // Pink up arrow slab (CVR L30 above the pink target): replaces the down / within / up case.
            $case = 'pink';
        } elseif ($aL30 <= 0) {
            // Sold 0 (CVR L30 = 0%): always CVR Down, whatever LT O CVR is.
            $case = 'down';
        } elseif ($cvr > $lto * (1 + $up)) {
            $case = 'up';
        } elseif ($cvr < $lto * (1 - $down)) {
            $case = 'down';
        } else {
            $case = 'same';
        }
        $raw = $cfg[$case] ?? null;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }
        $pct = (float) $raw;
        $pct = $case === 'down' ? -abs($pct) : (($case === 'up' || $case === 'pink') ? abs($pct) : $pct);
        if ($pct == 0.0) {
            return null;
        }
        $next = $base * (1 + $pct / 100);
        $capped = false;
        // Upper cap: a new price above the cap price is brought down to it. CVR Up / pink never LOWER the price (see below).
        $ceil = ! empty($cfg['upper_cap']) ? self::ceilingPrice($row, $cfg, $channelAds) : null;
        if ($ceil !== null && is_finite($ceil) && $next > $ceil) {
            $next = $ceil;
            $capped = true;
        }
        $floor = ! empty($cfg['lower_cap']) ? self::floorPrice($row, $cfg, $channelAds) : null;
        if ($floor !== null && $next < $floor) {
            $next = is_finite($floor) ? $floor : $base;
            $capped = true;
        }
        // CVR Up / pink must never LOWER the price: not below the price it started from (the Upper cap can pull a high
        // price down to its ceiling).
        if ($pct > 0) {
            $next = max($next, $base);
        }
        // CVR Down must never RAISE the price: not above the price it started from (the Lower cap can lift a low price
        // up to its floor, which made a Down SKU go up), and not above the live price either.
        if ($pct < 0) {
            $next = min($next, $base);
            $live = (float) ($row['price'] ?? 0);
            if ($live > 0) {
                $next = min($next, $live);
            }
        }
        $next = round($next, 2);
        if (! ($next >= 0.01) || abs($next - $base) < 0.005) {
            return null;
        }

        return ['price' => $next, 'capped' => $capped];
    }

    /** Lowest price the Lower cap allows (higher of the Min NPFT% price and the Min NROI% price). INF = unreachable, null = no limit. */
    public static function floorPrice(array $row, array $cfg, float $channelAds): ?float
    {
        $lp = (float) ($row['lp'] ?? 0);
        $cost = $lp > 0 ? $lp : 0.0;
        $ship = (float) ($row['ship'] ?? 0);
        $margin = AmazonDilGroiRule::TAKE_HOME;
        $floor = null;
        if (isset($cfg['min_npft']) && is_numeric($cfg['min_npft'])) {
            $n = (float) $cfg['min_npft'];
            $ads = $channelAds;
            $k = $margin - ($n + $ads) / 100;
            $f = $k > 0 ? ($ship + $cost) / $k : INF;
            $floor = $floor === null ? $f : max($floor, $f);
        }
        if (isset($cfg['min_nroi']) && is_numeric($cfg['min_nroi']) && $cost > 0) {
            $r = (float) $cfg['min_nroi'];
            $ads = $channelAds;
            $k = $margin - $ads / 100;
            $f = $k > 0 ? ($cost * (1 + $r / 100) + $ship) / $k : INF;
            $floor = $floor === null ? $f : max($floor, $f);
        }

        return $floor;
    }

    /** Highest price the Upper cap allows (lower of the Max NPFT% price and the Max NROI% price). null = no limit. */
    public static function ceilingPrice(array $row, array $cfg, float $channelAds): ?float
    {
        $lp = (float) ($row['lp'] ?? 0);
        $cost = $lp > 0 ? $lp : 0.0;
        $ship = (float) ($row['ship'] ?? 0);
        $margin = AmazonDilGroiRule::TAKE_HOME;
        $ceil = null;
        if (isset($cfg['max_npft']) && is_numeric($cfg['max_npft'])) {
            $k = $margin - ((float) $cfg['max_npft'] + $channelAds) / 100;
            if ($k > 0) {
                $f = ($ship + $cost) / $k;
                $ceil = $ceil === null ? $f : min($ceil, $f);
            }
        }
        if (isset($cfg['max_nroi']) && is_numeric($cfg['max_nroi']) && $cost > 0) {
            $k = $margin - $channelAds / 100;
            if ($k > 0) {
                $f = ($cost * (1 + (float) $cfg['max_nroi'] / 100) + $ship) / $k;
                $ceil = $ceil === null ? $f : min($ceil, $f);
            }
        }

        return $ceil;
    }

    private static function band(mixed $v, float $default, float $max): float
    {
        return (is_numeric($v) && (float) $v >= 0 && (float) $v < $max + 1) ? (float) $v : $default;
    }
}
