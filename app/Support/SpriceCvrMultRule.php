<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * CVR-trend SPRICE multipliers for Amazon (and shared storage for ebay/temu/shopify UIs).
 *
 * Trend = today CVR vs previous recorded day (same tol as CVR L30 arrows).
 *
 * Five dynamic CVR rules:
 *   1 Red    : CVR = 0% → Dil% bands → target GROI%
 *   2 Yellow : 0.01 – 3.5 (fixed; stored action key: red / low_cvr=3.5)
 *              Per-rule Down/Equal/Up % via slab_pct (Yellow defaults 2/1/1; others 1/1/1)
 *   3 Blue   : 3.51 – mid_cvr (default 3.51–7)
 *              Equal/Up: if Dil% > blue_*_dil (default 100) → Increase
 *   4 Green  : mid+0.01 – high (default 7.01–13)
 *              Equal/Up: if Dil% > green_*_dil (default 100) → Increase
 *   5 Pink   : > high+0.01 (default >13.01)
 *              Down/Equal/Up: if Dil% > pink_*_dil (default 100) → Increase
 * Per Yellow/Blue/Green/Pink, Down / Equal / Up actions are configurable: increase | decrease | hold.
 *
 * After multiply:
 *   - floor so Sroi (gross) ≥ roi_floor_pct:
 *       floor = (LP × (1 + roi_floor_pct/100) + Ship) / 0.80
 *   - no decrease when Dil% (L30/INV×100) > 100
 *
 * Rule 1 when CVR L30 = 0%:
 *   - Dil% < dil_low      → suggest SPRICE at roi_low GROI%
 *   - Dil% dil_low–dil_high → suggest SPRICE at roi_mid GROI%
 *   - Dil% > dil_high     → suggest SPRICE at roi_high GROI%
 *   (SPRICE = (LP × (1 + GROI%/100) + Ship) / 0.80)
 *
 * Stored in ebay_sbid_rules under key ebay_sprice_cvr.
 */
final class SpriceCvrMultRule
{
    public const KEY = 'ebay_sprice_cvr';

    public const DEFAULT_LOW_CVR = 3.5;

    public const DEFAULT_MID_CVR = 7.0;

    public const DEFAULT_HIGH_CVR = 13.0;

    public const DEFAULT_DOWN_MULT = 0.99;

    public const DEFAULT_UP_MULT = 1.01;

    public const DEFAULT_ROI_FLOOR_PCT = 40.0;

    public const DEFAULT_TREND_TOLERANCE = 0.1;

    /** Rule 3/4 Blue|Green Equal/Up: Increase when Dil% (L30/INV×100) exceeds this */
    public const DEFAULT_DIL_OVERRIDE = 100.0;

    public const DEFAULT_BLUE_UP_DIL = self::DEFAULT_DIL_OVERRIDE;

    public const DEFAULT_GREEN_UP_DIL = self::DEFAULT_DIL_OVERRIDE;

    public const DEFAULT_ZERO_CVR_ENABLED = true;

    public const DEFAULT_ZERO_CVR_DIL_LOW = 25.0;

    public const DEFAULT_ZERO_CVR_DIL_HIGH = 50.0;

    public const DEFAULT_ZERO_CVR_ROI_LOW = 40.0;

    public const DEFAULT_ZERO_CVR_ROI_MID = 50.0;

    public const DEFAULT_ZERO_CVR_ROI_HIGH = 60.0;

    /** Yellow (red) Down vs prev: adjust SPRICE by this % */
    public const DEFAULT_YELLOW_DOWN_PCT = 2.0;

    /** Yellow (red) Equal vs prev: adjust SPRICE by this % */
    public const DEFAULT_YELLOW_EQUAL_PCT = 1.0;

    /** Yellow (red) Up vs prev: adjust SPRICE by this % */
    public const DEFAULT_YELLOW_UP_PCT = 1.0;

    /** @var list<string> */
    public const ACTIONS = ['increase', 'decrease', 'hold'];

    /**
     * @return array{
     *   down: string,
     *   equal: string,
     *   up: string
     * }
     */
    public static function defaultSlabActions(string $slab): array
    {
        return match ($slab) {
            'red' => ['down' => 'decrease', 'equal' => 'decrease', 'up' => 'hold'],
            'pink' => ['down' => 'hold', 'equal' => 'decrease', 'up' => 'increase'],
            default => ['down' => 'hold', 'equal' => 'hold', 'up' => 'hold'], // blue, green
        };
    }

    /**
     * @return array{down: float, equal: float, up: float}
     */
    public static function defaultYellowPct(): array
    {
        return [
            'down' => self::DEFAULT_YELLOW_DOWN_PCT,
            'equal' => self::DEFAULT_YELLOW_EQUAL_PCT,
            'up' => self::DEFAULT_YELLOW_UP_PCT,
        ];
    }

    /**
     * Default ±% for Blue / Green / Pink trend rows (replaces global ×0.99/1.01 UI).
     *
     * @return array{down: float, equal: float, up: float}
     */
    public static function defaultBandPct(): array
    {
        return [
            'down' => 1.0,
            'equal' => 1.0,
            'up' => 1.0,
        ];
    }

    /**
     * @return array{
     *   red: array{down: float, equal: float, up: float},
     *   blue: array{down: float, equal: float, up: float},
     *   green: array{down: float, equal: float, up: float},
     *   pink: array{down: float, equal: float, up: float}
     * }
     */
    public static function defaultSlabPct(): array
    {
        return [
            'red' => self::defaultYellowPct(),
            'blue' => self::defaultBandPct(),
            'green' => self::defaultBandPct(),
            'pink' => self::defaultBandPct(),
        ];
    }

    /**
     * @param  array<string, mixed>  $in
     * @param  array{down: float, equal: float, up: float}|null  $def
     * @return array{down: float, equal: float, up: float}
     */
    public static function sanitizeTrendPct(array $in, ?array $def = null): array
    {
        $def = $def ?? self::defaultBandPct();
        $down = isset($in['down']) && is_numeric($in['down']) ? (float) $in['down'] : $def['down'];
        $equal = isset($in['equal']) && is_numeric($in['equal']) ? (float) $in['equal'] : $def['equal'];
        $up = isset($in['up']) && is_numeric($in['up']) ? (float) $in['up'] : $def['up'];
        if ($down < 0.1 || $down > 50) {
            $down = $def['down'];
        }
        if ($equal < 0.1 || $equal > 50) {
            $equal = $def['equal'];
        }
        if ($up < 0.1 || $up > 50) {
            $up = $def['up'];
        }

        return [
            'down' => round($down, 2),
            'equal' => round($equal, 2),
            'up' => round($up, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array{down: float, equal: float, up: float}
     */
    public static function sanitizeYellowPct(array $in): array
    {
        return self::sanitizeTrendPct($in, self::defaultYellowPct());
    }

    /**
     * @param  array<string, mixed>  $in
     * @param  array{down: float, equal: float, up: float}|null  $yellowLegacy
     * @return array{
     *   red: array{down: float, equal: float, up: float},
     *   blue: array{down: float, equal: float, up: float},
     *   green: array{down: float, equal: float, up: float},
     *   pink: array{down: float, equal: float, up: float}
     * }
     */
    public static function sanitizeSlabPct(array $in, ?array $yellowLegacy = null): array
    {
        $def = self::defaultSlabPct();
        $out = [];
        foreach (['red', 'blue', 'green', 'pink'] as $slab) {
            $row = isset($in[$slab]) && is_array($in[$slab]) ? $in[$slab] : [];
            if ($slab === 'red' && $row === [] && $yellowLegacy !== null) {
                $row = $yellowLegacy;
            }
            $out[$slab] = self::sanitizeTrendPct($row, $def[$slab]);
        }

        return $out;
    }

    /**
     * @return array{
     *   red: array{down: string, equal: string, up: string},
     *   blue: array{down: string, equal: string, up: string},
     *   green: array{down: string, equal: string, up: string},
     *   pink: array{down: string, equal: string, up: string}
     * }
     */
    public static function defaultActions(): array
    {
        return [
            'red' => self::defaultSlabActions('red'),
            'blue' => self::defaultSlabActions('blue'),
            'green' => self::defaultSlabActions('green'),
            'pink' => self::defaultSlabActions('pink'),
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   dil_low: float,
     *   dil_high: float,
     *   roi_low: float,
     *   roi_mid: float,
     *   roi_high: float
     * }
     */
    public static function defaultZeroCvrDil(): array
    {
        return [
            'enabled' => self::DEFAULT_ZERO_CVR_ENABLED,
            'dil_low' => self::DEFAULT_ZERO_CVR_DIL_LOW,
            'dil_high' => self::DEFAULT_ZERO_CVR_DIL_HIGH,
            'roi_low' => self::DEFAULT_ZERO_CVR_ROI_LOW,
            'roi_mid' => self::DEFAULT_ZERO_CVR_ROI_MID,
            'roi_high' => self::DEFAULT_ZERO_CVR_ROI_HIGH,
        ];
    }

    public static function defaults(): array
    {
        return [
            'low_cvr' => self::DEFAULT_LOW_CVR,
            'mid_cvr' => self::DEFAULT_MID_CVR,
            'high_cvr' => self::DEFAULT_HIGH_CVR,
            'down_mult' => self::DEFAULT_DOWN_MULT,
            'up_mult' => self::DEFAULT_UP_MULT,
            'roi_floor_pct' => self::DEFAULT_ROI_FLOOR_PCT,
            'trend_tolerance' => self::DEFAULT_TREND_TOLERANCE,
            'actions' => self::defaultActions(),
            'yellow_pct' => self::defaultYellowPct(),
            'slab_pct' => self::defaultSlabPct(),
            'blue_equal_dil' => self::DEFAULT_DIL_OVERRIDE,
            'blue_up_dil' => self::DEFAULT_BLUE_UP_DIL,
            'green_equal_dil' => self::DEFAULT_DIL_OVERRIDE,
            'green_up_dil' => self::DEFAULT_GREEN_UP_DIL,
            'pink_down_dil' => self::DEFAULT_DIL_OVERRIDE,
            'pink_equal_dil' => self::DEFAULT_DIL_OVERRIDE,
            'pink_up_dil' => self::DEFAULT_DIL_OVERRIDE,
            'zero_cvr_dil' => self::defaultZeroCvrDil(),
        ];
    }

    public static function sanitizeUpDilThreshold(mixed $in, float $default): float
    {
        $v = is_numeric($in) ? (float) $in : $default;
        if ($v < 0 || $v > 500) {
            $v = $default;
        }

        return round($v, 2);
    }

    public static function sanitizeDilOverride(mixed $in): float
    {
        return self::sanitizeUpDilThreshold($in, self::DEFAULT_DIL_OVERRIDE);
    }

    public static function sanitizeBlueUpDil(mixed $in): float
    {
        return self::sanitizeUpDilThreshold($in, self::DEFAULT_BLUE_UP_DIL);
    }

    public static function sanitizeGreenUpDil(mixed $in): float
    {
        return self::sanitizeUpDilThreshold($in, self::DEFAULT_GREEN_UP_DIL);
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array{
     *   enabled: bool,
     *   dil_low: float,
     *   dil_high: float,
     *   roi_low: float,
     *   roi_mid: float,
     *   roi_high: float
     * }
     */
    public static function sanitizeZeroCvrDil(array $in): array
    {
        $def = self::defaultZeroCvrDil();
        $enabled = array_key_exists('enabled', $in)
            ? filter_var($in['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : $def['enabled'];
        if ($enabled === null) {
            $enabled = $def['enabled'];
        }

        $dilLow = isset($in['dil_low']) && is_numeric($in['dil_low']) ? (float) $in['dil_low'] : $def['dil_low'];
        $dilHigh = isset($in['dil_high']) && is_numeric($in['dil_high']) ? (float) $in['dil_high'] : $def['dil_high'];
        $roiLow = isset($in['roi_low']) && is_numeric($in['roi_low']) ? (float) $in['roi_low'] : $def['roi_low'];
        $roiMid = isset($in['roi_mid']) && is_numeric($in['roi_mid']) ? (float) $in['roi_mid'] : $def['roi_mid'];
        $roiHigh = isset($in['roi_high']) && is_numeric($in['roi_high']) ? (float) $in['roi_high'] : $def['roi_high'];

        if ($dilLow < 0 || $dilLow > 500) {
            $dilLow = $def['dil_low'];
        }
        if ($dilHigh < 0 || $dilHigh > 500) {
            $dilHigh = $def['dil_high'];
        }
        if ($dilHigh < $dilLow) {
            $tmp = $dilLow;
            $dilLow = $dilHigh;
            $dilHigh = $tmp;
        }
        foreach ([&$roiLow, &$roiMid, &$roiHigh] as &$roi) {
            if ($roi < 0 || $roi > 500) {
                $roi = $def['roi_mid'];
            }
            $roi = round($roi, 2);
        }
        unset($roi);

        return [
            'enabled' => (bool) $enabled,
            'dil_low' => round($dilLow, 2),
            'dil_high' => round($dilHigh, 2),
            'roi_low' => $roiLow,
            'roi_mid' => $roiMid,
            'roi_high' => $roiHigh,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(string $key = self::KEY): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', $key)->first();
        $stored = $row ? json_decode($row->rule, true) : [];
        if (! is_array($stored)) {
            $stored = [];
        }

        return self::sanitize(array_merge(self::defaults(), $stored));
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array{down: string, equal: string, up: string}
     */
    public static function sanitizeSlabActions(array $in, string $slab): array
    {
        $defaults = self::defaultSlabActions($slab);
        $out = [];
        foreach (['down', 'equal', 'up'] as $trend) {
            $val = isset($in[$trend]) ? strtolower(trim((string) $in[$trend])) : '';
            $out[$trend] = in_array($val, self::ACTIONS, true) ? $val : $defaults[$trend];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    public static function sanitize(array $in): array
    {
        // Yellow end is fixed at 3.5% (range 0.01–3.5); ignore stale stored low_cvr (e.g. 6).
        $low = self::DEFAULT_LOW_CVR;
        $mid = isset($in['mid_cvr']) && is_numeric($in['mid_cvr'])
            ? (float) $in['mid_cvr']
            : self::DEFAULT_MID_CVR;
        $high = isset($in['high_cvr']) && is_numeric($in['high_cvr'])
            ? (float) $in['high_cvr']
            : self::DEFAULT_HIGH_CVR;
        $down = isset($in['down_mult']) && is_numeric($in['down_mult'])
            ? (float) $in['down_mult']
            : self::DEFAULT_DOWN_MULT;
        $up = isset($in['up_mult']) && is_numeric($in['up_mult'])
            ? (float) $in['up_mult']
            : self::DEFAULT_UP_MULT;
        $roiFloor = isset($in['roi_floor_pct']) && is_numeric($in['roi_floor_pct'])
            ? (float) $in['roi_floor_pct']
            : self::DEFAULT_ROI_FLOOR_PCT;
        $tol = isset($in['trend_tolerance']) && is_numeric($in['trend_tolerance'])
            ? (float) $in['trend_tolerance']
            : self::DEFAULT_TREND_TOLERANCE;

        if ($mid <= $low || $mid > 100) {
            $mid = self::DEFAULT_MID_CVR;
        }
        if ($high <= 0 || $high > 100) {
            $high = self::DEFAULT_HIGH_CVR;
        }
        if ($high <= $mid) {
            $high = min(100.0, $mid + (self::DEFAULT_HIGH_CVR - self::DEFAULT_MID_CVR));
        }
        if ($high <= $mid) {
            $low = self::DEFAULT_LOW_CVR;
            $mid = self::DEFAULT_MID_CVR;
            $high = self::DEFAULT_HIGH_CVR;
        }

        if ($down <= 0 || $down > 2) {
            $down = self::DEFAULT_DOWN_MULT;
        }
        if ($up <= 0 || $up > 2) {
            $up = self::DEFAULT_UP_MULT;
        }
        if ($roiFloor < 0 || $roiFloor > 500) {
            $roiFloor = self::DEFAULT_ROI_FLOOR_PCT;
        }
        if ($tol < 0 || $tol > 5) {
            $tol = self::DEFAULT_TREND_TOLERANCE;
        }

        $actionsIn = isset($in['actions']) && is_array($in['actions']) ? $in['actions'] : [];
        $actions = [
            'red' => self::sanitizeSlabActions(
                is_array($actionsIn['red'] ?? null) ? $actionsIn['red'] : [],
                'red'
            ),
            'blue' => self::sanitizeSlabActions(
                is_array($actionsIn['blue'] ?? null) ? $actionsIn['blue'] : [],
                'blue'
            ),
            'green' => self::sanitizeSlabActions(
                is_array($actionsIn['green'] ?? null) ? $actionsIn['green'] : [],
                'green'
            ),
            'pink' => self::sanitizeSlabActions(
                is_array($actionsIn['pink'] ?? null) ? $actionsIn['pink'] : [],
                'pink'
            ),
        ];

        $zeroIn = [];
        if (isset($in['zero_cvr_dil']) && is_array($in['zero_cvr_dil'])) {
            $zeroIn = $in['zero_cvr_dil'];
        } else {
            // Flat keys from form posts (zero_cvr_dil[enabled], etc.)
            foreach (['enabled', 'dil_low', 'dil_high', 'roi_low', 'roi_mid', 'roi_high'] as $k) {
                $flat = 'zero_cvr_dil_'.$k;
                if (array_key_exists($flat, $in)) {
                    $zeroIn[$k] = $in[$flat];
                } elseif (array_key_exists('zero_cvr_dil.'.$k, $in)) {
                    $zeroIn[$k] = $in['zero_cvr_dil.'.$k];
                }
            }
        }

        $yellowIn = [];
        if (isset($in['yellow_pct']) && is_array($in['yellow_pct'])) {
            $yellowIn = $in['yellow_pct'];
        } else {
            foreach (['down', 'equal', 'up'] as $k) {
                $flat = 'yellow_pct_'.$k;
                if (array_key_exists($flat, $in)) {
                    $yellowIn[$k] = $in[$flat];
                } elseif (array_key_exists('yellow_pct.'.$k, $in)) {
                    $yellowIn[$k] = $in['yellow_pct.'.$k];
                }
            }
        }
        $yellowPct = self::sanitizeYellowPct($yellowIn);

        $slabIn = [];
        if (isset($in['slab_pct']) && is_array($in['slab_pct'])) {
            $slabIn = $in['slab_pct'];
        } else {
            foreach (['red', 'blue', 'green', 'pink'] as $slab) {
                foreach (['down', 'equal', 'up'] as $k) {
                    $flat = 'slab_pct_'.$slab.'_'.$k;
                    if (array_key_exists($flat, $in)) {
                        $slabIn[$slab][$k] = $in[$flat];
                    } elseif (array_key_exists('slab_pct.'.$slab.'.'.$k, $in)) {
                        $slabIn[$slab][$k] = $in['slab_pct.'.$slab.'.'.$k];
                    }
                }
            }
        }
        $slabPct = self::sanitizeSlabPct($slabIn, $yellowPct);

        return [
            'low_cvr' => round($low, 2),
            'mid_cvr' => round($mid, 2),
            'high_cvr' => round($high, 2),
            'down_mult' => round($down, 4),
            'up_mult' => round($up, 4),
            'roi_floor_pct' => round($roiFloor, 2),
            'trend_tolerance' => round($tol, 3),
            'actions' => $actions,
            'yellow_pct' => $slabPct['red'],
            'slab_pct' => $slabPct,
            'blue_equal_dil' => self::sanitizeDilOverride($in['blue_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'blue_up_dil' => self::sanitizeBlueUpDil($in['blue_up_dil'] ?? self::DEFAULT_BLUE_UP_DIL),
            'green_equal_dil' => self::sanitizeDilOverride($in['green_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'green_up_dil' => self::sanitizeGreenUpDil($in['green_up_dil'] ?? self::DEFAULT_GREEN_UP_DIL),
            'pink_down_dil' => self::sanitizeDilOverride($in['pink_down_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'pink_equal_dil' => self::sanitizeDilOverride($in['pink_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'pink_up_dil' => self::sanitizeDilOverride($in['pink_up_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'zero_cvr_dil' => self::sanitizeZeroCvrDil($zeroIn),
        ];
    }
}
