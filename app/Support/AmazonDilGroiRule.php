<?php

namespace App\Support;

/**
 * Dil% slabs → Target GROI% → Sprc Dil.
 * Used by Amazon (`amazon_dil_vs_groi`), eBay 1–3 (`ebay1_dil_vs_groi` / `ebay2_dil_vs_groi` / `ebay3_dil_vs_groi`), and Temu 1–2 (`temu_dil_vs_groi` / `temu2_dil_vs_groi`).
 * First-time defaults: five slabs 0.1–25%. Add/delete is allowed; match is by min/max.
 */
class AmazonDilGroiRule
{
    public const TAKE_HOME = 0.80;

    /**
     * @return list<array{key:string,label:string,min:float,max:float,groi:float|int}>
     */
    public static function defaults(): array
    {
        return [
            self::make(0.1, 5.0, 50),
            self::make(5.0, 10.0, 55),
            self::make(10.0, 15.0, 60),
            self::make(15.0, 20.0, 65),
            self::make(20.0, 25.0, 70),
        ];
    }

    /**
     * @return array{key:string,label:string,min:float,max:float,groi:float|int}
     */
    public static function make(float $min, float $max, float $groi): array
    {
        $min = round($min, 2);
        $max = round($max, 2);
        $groi = round($groi, 2);
        if ($groi < 0) {
            $groi = 0.0;
        }

        return [
            'key' => self::keyFor($min, $max),
            'label' => self::labelFor($min, $max),
            'min' => $min,
            'max' => $max,
            'groi' => $groi,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{key:string,label:string,min:float,max:float,groi:float}|null
     */
    public static function normalize(array $item): ?array
    {
        $min = isset($item['min']) && is_numeric($item['min']) ? (float) $item['min'] : null;
        $max = isset($item['max']) && is_numeric($item['max']) ? (float) $item['max'] : null;
        if ($min === null || $max === null) {
            $key = (string) ($item['key'] ?? '');
            if (preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $key, $m) === 1) {
                $min = (float) $m[1];
                $max = (float) $m[2];
            }
        }
        if ($min === null || $max === null || $min < 0 || $max <= $min) {
            return null;
        }
        $groi = is_numeric($item['groi'] ?? null) ? (float) $item['groi'] : 0.0;

        return self::make($min, $max, $groi);
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<array{key:string,label:string,min:float,max:float,groi:float}>
     */
    public static function normalizeList(array $rules): array
    {
        $out = [];
        foreach ($rules as $item) {
            if (! is_array($item)) {
                continue;
            }
            $rule = self::normalize($item);
            if ($rule !== null) {
                $out[] = $rule;
            }
        }
        usort($out, static fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        return array_values($out);
    }

    /**
     * Dil% → first matching slab (min ≤ Dil < max; last slab includes max).
     *
     * @param  list<array<string, mixed>>  $rules
     * @return array{key:string,label:string,min:float,max:float,groi:float}|null
     */
    public static function match(float $dil, array $rules): ?array
    {
        if (! is_finite($dil)) {
            return null;
        }
        $list = self::normalizeList($rules);
        $last = count($list) - 1;
        foreach ($list as $i => $rule) {
            $hiOk = $i === $last ? $dil <= $rule['max'] : $dil < $rule['max'];
            if ($dil >= $rule['min'] && $hiOk) {
                return $rule;
            }
        }

        return null;
    }

    /** Dil% → slab key, or null when no slab matches. */
    public static function slabKey(float $dil, ?array $rules = null): ?string
    {
        $rule = self::match($dil, $rules ?? self::defaults());

        return $rule['key'] ?? null;
    }

    /**
     * Lowest Target GROI% in the table (used when A L30 = 0).
     *
     * @param  list<array<string, mixed>>  $rules
     */
    public static function minTarget(array $rules): ?float
    {
        $list = self::normalizeList($rules);
        $min = null;
        foreach ($list as $rule) {
            $g = (float) $rule['groi'];
            if ($min === null || $g < $min) {
                $min = $g;
            }
        }

        return $min;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public static function groiForDil(float $dil, array $rules): ?float
    {
        $rule = self::match($dil, $rules);
        if ($rule === null) {
            return null;
        }

        return (float) $rule['groi'];
    }

    /**
     * Suggested price so GROI% = target:
     * (LP × (1 + GROI%/100) + Ship) / 0.80
     */
    public static function suggestedPrice(float $lp, float $ship, float $groiPct): ?float
    {
        if (! is_finite($lp) || $lp <= 0) {
            return null;
        }
        if (! is_finite($ship)) {
            $ship = 0.0;
        }
        if (! is_finite($groiPct)) {
            return null;
        }
        $price = ($lp * (1 + $groiPct / 100) + $ship) / self::TAKE_HOME;
        if (! is_finite($price) || $price <= 0) {
            return null;
        }

        return round($price, 2);
    }

    /** Same as the Amazon tabulator CVR L30 column (A L30 ÷ Sess30). */
    public static function cvrL30(float $aL30, float $sess30): float
    {
        if (! is_finite($sess30) || $sess30 <= 0) {
            return 0.0;
        }

        return ($aL30 / $sess30) * 100;
    }

    /** Same as the Amazon tabulator CVR L45 baseline used for the Up/Down arrow. */
    public static function cvrL45(float $aL30, float $sess30, float $aL60, float $sess60): float
    {
        $sess45 = ($sess30 + $sess60) / 2;
        if (! is_finite($sess45) || $sess45 <= 0) {
            return 0.0;
        }

        return ((($aL30 + $aL60) / 2) / $sess45) * 100;
    }

    /**
     * Same as amzPefCvrTrend: CVR L30 vs CVR L45, ±0.1% tolerance.
     * CVR L30 of 0 is always Down.
     *
     * @return 'down'|'up'|'flat'
     */
    public static function cvrTrend(float $cvrL30, float $cvrL45, float $tol = 0.1): string
    {
        if (! is_finite($cvrL30) || $cvrL30 <= 0 || $cvrL30 < $cvrL45 - $tol) {
            return 'down';
        }
        if ($cvrL30 > $cvrL45 + $tol) {
            return 'up';
        }

        return 'flat';
    }

    /**
     * Target GROI% after the Amazon CVR overlay:
     * Down and CVR L30 < 7% → −10 points; Up and CVR L30 > 10% → +10 points.
     */
    public static function adjustGroiForCvr(float $groi, float $cvrL30, string $trend): float
    {
        $adj = 0.0;
        if ($trend === 'down' && $cvrL30 < 7) {
            $adj = -10.0;
        } elseif ($trend === 'up' && $cvrL30 > 10) {
            $adj = 10.0;
        }
        $out = $groi + $adj;
        if (! is_finite($out) || $out < 0) {
            return 0.0;
        }

        return round($out, 2);
    }

    public static function keyFor(float $min, float $max): string
    {
        return self::fmtNum($min).'-'.self::fmtNum($max);
    }

    public static function labelFor(float $min, float $max): string
    {
        return self::fmtNum($min).'–'.self::fmtNum($max).'%';
    }

    private static function fmtNum(float $n): string
    {
        $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}
