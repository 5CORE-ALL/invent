<?php

namespace App\Support;

/**
 * Amazon coupon chosen per SKU by TWO conditions that must both hold:
 *   1. AL30 Dil %  = A L30 (Amazon units, last 30 days) ÷ INV × 100  (INV must be above 0)
 *   2. Price       = Amazon listing price
 *
 * A rule row is { dmin, dmax, pmin, pmax, pct }. A SKU gets the coupon of the FIRST rule
 * (top to bottom) whose Dil range AND price range both contain it. The 0–0 Dil range means
 * A L30 = 0. A coupon percent below 5 or above 80 is off (Amazon percentage coupons: 5–80%).
 */
class AmazonDilPriceCoupon
{
    public const MAX_RULES = 40;

    /**
     * Stable key for a rule's two ranges, used to store its daily SKU count history.
     * The page builds the same key (String(Math.round(x * 100) / 100)).
     */
    public static function ruleKey(float $dmin, float $dmax, float $pmin, float $pmax): string
    {
        return 'd'.(string) round($dmin, 2).'-'.(string) round($dmax, 2)
            .'|p'.(string) round($pmin, 2).'-'.(string) round($pmax, 2);
    }

    /**
     * @return list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>
     */
    public static function defaults(): array
    {
        $any = 99999.0;

        return [
            ['dmin' => 0.0, 'dmax' => 0.0, 'pmin' => 0.0, 'pmax' => $any, 'pct' => 0],
            ['dmin' => 0.01, 'dmax' => 10.0, 'pmin' => 0.0, 'pmax' => $any, 'pct' => 0],
            ['dmin' => 10.0, 'dmax' => 25.0, 'pmin' => 0.0, 'pmax' => $any, 'pct' => 0],
            ['dmin' => 25.0, 'dmax' => 50.0, 'pmin' => 0.0, 'pmax' => $any, 'pct' => 0],
            ['dmin' => 50.0, 'dmax' => 100.0, 'pmin' => 0.0, 'pmax' => $any, 'pct' => 0],
            ['dmin' => 100.0, 'dmax' => 9999.0, 'pmin' => 0.0, 'pmax' => $any, 'pct' => 0],
        ];
    }

    public static function couponPercent(mixed $pct): int
    {
        if (! is_numeric($pct)) {
            return 0;
        }
        $n = (int) round((float) $pct);

        return ($n < 5 || $n > 80) ? 0 : $n;
    }

    /**
     * @return list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>
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
            foreach (['dmin', 'dmax', 'pmin', 'pmax'] as $field) {
                if (! is_numeric($row[$field] ?? null)) {
                    continue 2;
                }
            }
            $dmin = round((float) $row['dmin'], 2);
            $dmax = round((float) $row['dmax'], 2);
            $pmin = max(0.0, round((float) $row['pmin'], 2));
            $pmax = max(0.0, round((float) $row['pmax'], 2));
            if ($dmax < $dmin) {
                [$dmin, $dmax] = [$dmax, $dmin];
            }
            if ($pmax < $pmin) {
                [$pmin, $pmax] = [$pmax, $pmin];
            }
            $out[] = [
                'dmin' => $dmin,
                'dmax' => $dmax,
                'pmin' => $pmin,
                'pmax' => $pmax,
                'pct' => self::couponPercent($row['pct'] ?? 0),
            ];
            if (count($out) >= self::MAX_RULES) {
                break;
            }
        }

        return $out;
    }

    /**
     * Index of the first rule matching this SKU, or -1. $inv must be above 0.
     *
     * @param  list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>  $rules
     */
    public static function matchIndex(float $l30, float $inv, float $price, array $rules): int
    {
        if ($inv <= 0 || $price <= 0) {
            return -1;
        }
        $dil = ($l30 / $inv) * 100;
        foreach ($rules as $i => $rule) {
            $dmin = (float) ($rule['dmin'] ?? 0);
            $dmax = (float) ($rule['dmax'] ?? 0);
            $dilOk = (abs($dmin) < 0.0000001 && abs($dmax) < 0.0000001)
                ? abs($l30) < 0.0000001
                : ($dil >= $dmin && $dil <= $dmax);
            if (! $dilOk) {
                continue;
            }
            if ($price >= (float) ($rule['pmin'] ?? 0) && $price <= (float) ($rule['pmax'] ?? 0)) {
                return (int) $i;
            }
        }

        return -1;
    }

    /**
     * Coupon percent for one SKU. 0 = no coupon.
     *
     * @param  list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>  $rules
     */
    public static function percentFor(float $l30, float $inv, float $price, array $rules): int
    {
        $i = self::matchIndex($l30, $inv, $price, $rules);

        return $i < 0 ? 0 : self::couponPercent($rules[$i]['pct'] ?? 0);
    }
}
