<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * CVR-trend SPRICE multipliers for Amazon (and shared storage for ebay/temu/shopify UIs).
 *
 * Trend = CVR L30 vs prior period L31–L60 (CVR L60 column; same ±0.1% tol as arrows).
 *
 * Five dynamic CVR rules:
 *   1 Red    : CVR = 0% — CVR trend Down/Equal/Up (key: zero; defaults like Yellow)
 *   2 Yellow : 0.01 – 3.5 (fixed; stored action key: red / low_cvr=3.5)
 *              Per-rule Down/Equal/Up signed % via slab_pct (Yellow defaults −2/−1/0)
 *   3 Blue   : 3.51 – mid_cvr (default 3.51–7)
 *   4 Green  : mid+0.01 – high (default 7.01–13)
 *   5 Pink   : > high+0.01 (default >13.01)
 * Signed slab_pct is % of PRICE (not PFT/GROI): SPRICE = price × (1 + pct/100).
 *   +N increase price, −N decrease price, 0 = hold at Amazon price (0% change).
 * Legacy actions (increase|decrease|hold) are derived from the sign of slab_pct.
 * Dil override thresholds are stored for compat but unused by the UI/apply path.
 *
 * Rule 1 when CVR L30 = 0%: same CVR-trend format as Yellow (actions.zero)
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

    /** Dil overrides: all rules Down/Equal/Up → Increase when Dil% exceeds this */
    public const DEFAULT_DIL_OVERRIDE = 100.0;

    public const DEFAULT_ZERO_UP_DIL = self::DEFAULT_DIL_OVERRIDE;

    public const DEFAULT_YELLOW_UP_DIL = self::DEFAULT_DIL_OVERRIDE;

    public const DEFAULT_BLUE_UP_DIL = self::DEFAULT_DIL_OVERRIDE;

    public const DEFAULT_GREEN_UP_DIL = self::DEFAULT_DIL_OVERRIDE;

    public const DEFAULT_ZERO_CVR_ENABLED = true;

    public const DEFAULT_ZERO_CVR_DIL_LOW = 25.0;

    public const DEFAULT_ZERO_CVR_DIL_HIGH = 50.0;

    public const DEFAULT_ZERO_CVR_ROI_LOW = 40.0;

    public const DEFAULT_ZERO_CVR_ROI_MID = 50.0;

    public const DEFAULT_ZERO_CVR_ROI_HIGH = 60.0;

    /** Signed %: +increase, −decrease, 0=hold */
    public const DEFAULT_YELLOW_DOWN_PCT = -2.0;

    public const DEFAULT_YELLOW_EQUAL_PCT = -1.0;

    public const DEFAULT_YELLOW_UP_PCT = 0.0;

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
            'zero' => ['down' => 'decrease', 'equal' => 'decrease', 'up' => 'hold'],
            'red' => ['down' => 'decrease', 'equal' => 'decrease', 'up' => 'hold'],
            'blue' => ['down' => 'decrease', 'equal' => 'hold', 'up' => 'increase'],
            'pink' => ['down' => 'hold', 'equal' => 'decrease', 'up' => 'increase'],
            default => ['down' => 'hold', 'equal' => 'hold', 'up' => 'hold'], // green
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
            'down' => 0.0,
            'equal' => 0.0,
            'up' => 0.0,
        ];
    }

    /** Blue default: Down −1 / Same 0 / Up +1 (% of price). */
    public static function defaultBluePct(): array
    {
        return [
            'down' => -1.0,
            'equal' => 0.0,
            'up' => 1.0,
        ];
    }

    public static function defaultPinkPct(): array
    {
        return [
            'down' => 0.0,
            'equal' => -1.0,
            'up' => 1.0,
        ];
    }

    /**
     * @return array{
     *   zero: array{down: float, equal: float, up: float},
     *   red: array{down: float, equal: float, up: float},
     *   blue: array{down: float, equal: float, up: float},
     *   green: array{down: float, equal: float, up: float},
     *   pink: array{down: float, equal: float, up: float}
     * }
     */
    /** Rule 1 CVR=0%: Down −1 / Same −1 / Up 0 (% of price). */
    public static function defaultZeroPct(): array
    {
        return [
            'down' => -1.0,
            'equal' => -1.0,
            'up' => 0.0,
        ];
    }

    public static function defaultSlabPct(): array
    {
        return [
            'zero' => self::defaultZeroPct(),
            'red' => self::defaultYellowPct(),
            'blue' => self::defaultBluePct(),
            'green' => self::defaultBandPct(),
            'pink' => self::defaultPinkPct(),
        ];
    }

    /**
     * Signed trend % (−50…+50). 0 = hold.
     *
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
        if ($down < -50 || $down > 50) {
            $down = $def['down'];
        }
        if ($equal < -50 || $equal > 50) {
            $equal = $def['equal'];
        }
        if ($up < -50 || $up > 50) {
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
     *   zero: array{down: float, equal: float, up: float},
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
        foreach (['zero', 'red', 'blue', 'green', 'pink'] as $slab) {
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
     *   zero: array{down: string, equal: string, up: string},
     *   red: array{down: string, equal: string, up: string},
     *   blue: array{down: string, equal: string, up: string},
     *   green: array{down: string, equal: string, up: string},
     *   pink: array{down: string, equal: string, up: string}
     * }
     */
    public static function defaultActions(): array
    {
        return [
            'zero' => self::defaultSlabActions('zero'),
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
            'zero_down_dil' => self::DEFAULT_DIL_OVERRIDE,
            'zero_equal_dil' => self::DEFAULT_DIL_OVERRIDE,
            'zero_up_dil' => self::DEFAULT_ZERO_UP_DIL,
            'yellow_down_dil' => self::DEFAULT_DIL_OVERRIDE,
            'yellow_equal_dil' => self::DEFAULT_DIL_OVERRIDE,
            'yellow_up_dil' => self::DEFAULT_YELLOW_UP_DIL,
            'blue_down_dil' => self::DEFAULT_DIL_OVERRIDE,
            'blue_equal_dil' => self::DEFAULT_DIL_OVERRIDE,
            'blue_up_dil' => self::DEFAULT_BLUE_UP_DIL,
            'green_down_dil' => self::DEFAULT_DIL_OVERRIDE,
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

    public static function sanitizeZeroUpDil(mixed $in): float
    {
        return self::sanitizeUpDilThreshold($in, self::DEFAULT_ZERO_UP_DIL);
    }

    public static function sanitizeYellowUpDil(mixed $in): float
    {
        return self::sanitizeUpDilThreshold($in, self::DEFAULT_YELLOW_UP_DIL);
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

    public static function actionFromSignedPct(float $v): string
    {
        if (abs($v) < 0.00001) {
            return 'hold';
        }

        return $v > 0 ? 'increase' : 'decrease';
    }

    /**
     * @param  array<string, array{down: float, equal: float, up: float}>  $slabPct
     * @return array<string, array{down: string, equal: string, up: string}>
     */
    public static function actionsFromSlabPct(array $slabPct): array
    {
        $out = [];
        foreach (['zero', 'red', 'blue', 'green', 'pink'] as $slab) {
            $row = isset($slabPct[$slab]) && is_array($slabPct[$slab])
                ? $slabPct[$slab]
                : self::defaultSlabPct()[$slab];
            $out[$slab] = [
                'down' => self::actionFromSignedPct((float) ($row['down'] ?? 0)),
                'equal' => self::actionFromSignedPct((float) ($row['equal'] ?? 0)),
                'up' => self::actionFromSignedPct((float) ($row['up'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * Legacy rows stored positive % + actions; signed format uses ≤0 (0=hold, negative=decrease).
     *
     * @param  array<string, mixed>  $slabIn
     * @param  array<string, mixed>  $actionsIn
     * @param  array{down: float, equal: float, up: float}|null  $yellowLegacy
     * @return array{
     *   zero: array{down: float, equal: float, up: float},
     *   red: array{down: float, equal: float, up: float},
     *   blue: array{down: float, equal: float, up: float},
     *   green: array{down: float, equal: float, up: float},
     *   pink: array{down: float, equal: float, up: float}
     * }
     */
    public static function migrateLegacySlabPctToSigned(array $slabIn, array $actionsIn, ?array $yellowLegacy = null): array
    {
        $looksSigned = false;
        foreach (['zero', 'red', 'blue', 'green', 'pink'] as $slab) {
            $row = isset($slabIn[$slab]) && is_array($slabIn[$slab]) ? $slabIn[$slab] : null;
            if ($row === null) {
                continue;
            }
            foreach (['down', 'equal', 'up'] as $k) {
                if (isset($row[$k]) && is_numeric($row[$k]) && (float) $row[$k] <= 0) {
                    $looksSigned = true;
                    break 2;
                }
            }
        }
        if ($looksSigned) {
            return self::sanitizeSlabPct($slabIn, $yellowLegacy);
        }

        $def = self::defaultSlabPct();
        $out = [];
        foreach (['zero', 'red', 'blue', 'green', 'pink'] as $slab) {
            $row = isset($slabIn[$slab]) && is_array($slabIn[$slab]) ? $slabIn[$slab] : [];
            if ($slab === 'red' && $row === [] && $yellowLegacy !== null) {
                $row = $yellowLegacy;
            }
            $mag = self::sanitizeTrendPct([
                'down' => isset($row['down']) && is_numeric($row['down']) ? abs((float) $row['down']) : abs($def[$slab]['down']),
                'equal' => isset($row['equal']) && is_numeric($row['equal']) ? abs((float) $row['equal']) : abs($def[$slab]['equal']),
                'up' => isset($row['up']) && is_numeric($row['up']) ? abs((float) $row['up']) : abs($def[$slab]['up']),
            ], [
                'down' => abs($def[$slab]['down']) ?: 1.0,
                'equal' => abs($def[$slab]['equal']) ?: 1.0,
                'up' => abs($def[$slab]['up']) ?: 1.0,
            ]);
            $acts = self::sanitizeSlabActions(
                is_array($actionsIn[$slab] ?? null) ? $actionsIn[$slab] : [],
                $slab
            );
            $signed = [];
            foreach (['down', 'equal', 'up'] as $t) {
                if ($acts[$t] === 'hold') {
                    $signed[$t] = 0.0;
                } elseif ($acts[$t] === 'decrease') {
                    $signed[$t] = -abs($mag[$t]);
                } else {
                    $signed[$t] = abs($mag[$t]);
                }
            }
            $out[$slab] = self::sanitizeTrendPct($signed, $def[$slab]);
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
            foreach (['zero', 'red', 'blue', 'green', 'pink'] as $slab) {
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
        // Accept signed % (−50…+50, 0=hold). Migrate legacy positive% + actions → signed.
        $slabPct = self::migrateLegacySlabPctToSigned($slabIn, $actionsIn, $yellowPct);
        $actions = self::actionsFromSlabPct($slabPct);

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
            'zero_down_dil' => self::sanitizeDilOverride($in['zero_down_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'zero_equal_dil' => self::sanitizeDilOverride($in['zero_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'zero_up_dil' => self::sanitizeZeroUpDil($in['zero_up_dil'] ?? self::DEFAULT_ZERO_UP_DIL),
            'yellow_down_dil' => self::sanitizeDilOverride($in['yellow_down_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'yellow_equal_dil' => self::sanitizeDilOverride($in['yellow_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'yellow_up_dil' => self::sanitizeYellowUpDil($in['yellow_up_dil'] ?? self::DEFAULT_YELLOW_UP_DIL),
            'blue_down_dil' => self::sanitizeDilOverride($in['blue_down_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'blue_equal_dil' => self::sanitizeDilOverride($in['blue_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'blue_up_dil' => self::sanitizeBlueUpDil($in['blue_up_dil'] ?? self::DEFAULT_BLUE_UP_DIL),
            'green_down_dil' => self::sanitizeDilOverride($in['green_down_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'green_equal_dil' => self::sanitizeDilOverride($in['green_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'green_up_dil' => self::sanitizeGreenUpDil($in['green_up_dil'] ?? self::DEFAULT_GREEN_UP_DIL),
            'pink_down_dil' => self::sanitizeDilOverride($in['pink_down_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'pink_equal_dil' => self::sanitizeDilOverride($in['pink_equal_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'pink_up_dil' => self::sanitizeDilOverride($in['pink_up_dil'] ?? self::DEFAULT_DIL_OVERRIDE),
            'zero_cvr_dil' => self::sanitizeZeroCvrDil($zeroIn),
        ];
    }
}
