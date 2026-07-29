<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * CVR-trend SPRICE multipliers for Amazon (and shared storage for ebay/temu/shopify UIs).
 *
 * Trend = today CVR vs previous recorded day (same tol as CVR L30 arrows).
 *
 * Three dynamic CVR slabs (defaults 0–7 / 7.01–13 / >13.01).
 * Per slab, Down / Equal / Up actions are configurable: increase | decrease | hold.
 *
 * After multiply:
 *   - floor so Sroi (gross) ≥ roi_floor_pct:
 *       floor = (LP × (1 + roi_floor_pct/100) + Ship) / 0.80
 *   - if cap_at_lmp: ceiling at manual SP (Standard Price), else LMP when > 0
 *   - blank LMP: no increase unless CVR > high_cvr (default 13%)
 *   - no decrease when Dil% (L30/INV×100) > 100
 *
 * Sub-rule when CVR L30 = 0%:
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

    public const DEFAULT_LOW_CVR = 7.0;

    public const DEFAULT_HIGH_CVR = 13.0;

    public const DEFAULT_DOWN_MULT = 0.99;

    public const DEFAULT_UP_MULT = 1.01;

    public const DEFAULT_ROI_FLOOR_PCT = 40.0;

    public const DEFAULT_TREND_TOLERANCE = 0.1;

    public const DEFAULT_CAP_AT_LMP = true;

    public const DEFAULT_ZERO_CVR_ENABLED = true;

    public const DEFAULT_ZERO_CVR_DIL_LOW = 25.0;

    public const DEFAULT_ZERO_CVR_DIL_HIGH = 50.0;

    public const DEFAULT_ZERO_CVR_ROI_LOW = 40.0;

    public const DEFAULT_ZERO_CVR_ROI_MID = 50.0;

    public const DEFAULT_ZERO_CVR_ROI_HIGH = 60.0;

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
            'red' => ['down' => 'decrease', 'equal' => 'increase', 'up' => 'hold'],
            'pink' => ['down' => 'hold', 'equal' => 'decrease', 'up' => 'increase'],
            default => ['down' => 'hold', 'equal' => 'hold', 'up' => 'hold'], // green
        };
    }

    /**
     * @return array{
     *   red: array{down: string, equal: string, up: string},
     *   green: array{down: string, equal: string, up: string},
     *   pink: array{down: string, equal: string, up: string}
     * }
     */
    public static function defaultActions(): array
    {
        return [
            'red' => self::defaultSlabActions('red'),
            'green' => self::defaultSlabActions('green'),
            'pink' => self::defaultSlabActions('pink'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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
            'high_cvr' => self::DEFAULT_HIGH_CVR,
            'down_mult' => self::DEFAULT_DOWN_MULT,
            'up_mult' => self::DEFAULT_UP_MULT,
            'roi_floor_pct' => self::DEFAULT_ROI_FLOOR_PCT,
            'trend_tolerance' => self::DEFAULT_TREND_TOLERANCE,
            'cap_at_lmp' => self::DEFAULT_CAP_AT_LMP,
            'actions' => self::defaultActions(),
            'zero_cvr_dil' => self::defaultZeroCvrDil(),
        ];
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
        $low = isset($in['low_cvr']) && is_numeric($in['low_cvr'])
            ? (float) $in['low_cvr']
            : self::DEFAULT_LOW_CVR;
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
        $capAtLmp = array_key_exists('cap_at_lmp', $in)
            ? filter_var($in['cap_at_lmp'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : self::DEFAULT_CAP_AT_LMP;
        if ($capAtLmp === null) {
            $capAtLmp = self::DEFAULT_CAP_AT_LMP;
        }

        if ($low <= 0 || $low > 100) {
            $low = self::DEFAULT_LOW_CVR;
        }
        if ($high <= 0 || $high > 100) {
            $high = self::DEFAULT_HIGH_CVR;
        }
        if ($low > $high) {
            $tmp = $low;
            $low = $high;
            $high = $tmp;
        }
        if ($high <= $low) {
            $high = min(100.0, $low + (self::DEFAULT_HIGH_CVR - self::DEFAULT_LOW_CVR));
            if ($high <= $low) {
                $low = self::DEFAULT_LOW_CVR;
                $high = self::DEFAULT_HIGH_CVR;
            }
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

        return [
            'low_cvr' => round($low, 2),
            'high_cvr' => round($high, 2),
            'down_mult' => round($down, 4),
            'up_mult' => round($up, 4),
            'roi_floor_pct' => round($roiFloor, 2),
            'trend_tolerance' => round($tol, 3),
            'cap_at_lmp' => (bool) $capAtLmp,
            'actions' => $actions,
            'zero_cvr_dil' => self::sanitizeZeroCvrDil($zeroIn),
        ];
    }
}
