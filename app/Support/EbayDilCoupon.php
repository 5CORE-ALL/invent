<?php

namespace App\Support;

/**
 * Public eBay coupon chosen by Dil slab.
 * Dil = OV L30 ÷ INV. INV 0 is not a Dil. The 0–0 slab is OV L30 = 0.
 * A coupon percent below 5 or above 80 is off. eBay codes start at 5%.
 */
class EbayDilCoupon
{
    /**
     * @return list<array{min:float,max:float,pct:int}>
     */
    public static function defaults(): array
    {
        return [
            ['min' => 0.0, 'max' => 0.0, 'pct' => 5],
            ['min' => 0.01, 'max' => 10.0, 'pct' => 0],
            ['min' => 10.0, 'max' => 25.0, 'pct' => 0],
            ['min' => 25.0, 'max' => 50.0, 'pct' => 0],
            ['min' => 50.0, 'max' => 100.0, 'pct' => 0],
            ['min' => 100.0, 'max' => 9999.0, 'pct' => 0],
        ];
    }

    /**
     * Older saves stored one percent for E L30 = 0. That percent stays on the 0–0 slab.
     *
     * @return list<array{min:float,max:float,pct:int}>
     */
    public static function fromLegacy(int $pct): array
    {
        $slabs = self::defaults();
        $slabs[0]['pct'] = self::couponPercent($pct);

        return $slabs;
    }

    /**
     * @return list<array{min:float,max:float,pct:int}>
     */
    public static function normalize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! is_numeric($row['min'] ?? null) || ! is_numeric($row['max'] ?? null)) {
                continue;
            }
            $min = round((float) $row['min'], 2);
            $max = round((float) $row['max'], 2);
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $out[] = [
                'min' => $min,
                'max' => $max,
                'pct' => self::couponPercent($row['pct'] ?? 0),
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    public static function couponPercent(mixed $pct): int
    {
        if (! is_numeric($pct)) {
            return 0;
        }
        $n = (int) round((float) $pct);
        if ($n < 5 || $n > 80) {
            return 0;
        }

        return $n;
    }

    /**
     * Percent for this OV L30 and INV. 0 means no coupon.
     *
     * @param  list<array{min:float,max:float,pct:int}>  $slabs
     */
    public static function percentFor(float $ov, float $inv, array $slabs): int
    {
        if ($inv <= 0 || $slabs === []) {
            return 0;
        }
        $dil = ($ov / $inv) * 100;
        $prevMax = null;
        foreach ($slabs as $slab) {
            if (self::contains($dil, $ov, $slab, $prevMax)) {
                return self::couponPercent($slab['pct'] ?? 0);
            }
            $prevMax = (float) ($slab['max'] ?? 0);
        }

        return 0;
    }

    /**
     * @param  array{min?:float,max?:float,pct?:int}  $slab
     */
    public static function contains(float $dil, float $ov, array $slab, ?float $prevMax): bool
    {
        $min = (float) ($slab['min'] ?? 0);
        $max = (float) ($slab['max'] ?? 0);
        if (abs($min) < 0.0000001 && abs($max) < 0.0000001) {
            return abs($ov) < 0.0000001;
        }
        $shares = $prevMax !== null && abs($min - $prevMax) < 0.0001;
        $loOk = $shares ? $dil > $min : $dil >= $min;

        return $loOk && $dil <= $max;
    }

    /**
     * @param  list<array{min:float,max:float,pct:int}>  $slabs
     */
    public static function legacyPct(array $slabs): int
    {
        foreach ($slabs as $slab) {
            $min = (float) ($slab['min'] ?? 0);
            $max = (float) ($slab['max'] ?? 0);
            if (abs($min) < 0.0000001 && abs($max) < 0.0000001) {
                return self::couponPercent($slab['pct'] ?? 0);
            }
        }
        foreach ($slabs as $slab) {
            $pct = self::couponPercent($slab['pct'] ?? 0);
            if ($pct >= 5) {
                return $pct;
            }
        }

        return 0;
    }
}
