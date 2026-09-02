<?php

namespace App\Support;

/**
 * Amazon Analytics (/amazon-tabulator-view) Dil% slabs → Target GROI% → Sprc Dil.
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
            self::make(0.1, 5.0, 70),
            self::make(5.0, 10.0, 65),
            self::make(10.0, 15.0, 60),
            self::make(15.0, 20.0, 55),
            self::make(20.0, 25.0, 50),
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
