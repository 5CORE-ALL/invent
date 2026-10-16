<?php

namespace App\Support;

/**
 * eBay 1 volume-pricing discounts.
 * Buy 2 / Buy 3 / Buy 4 = weight slab + Dil range + Std NPFT % range.
 */
class EbayVolumePricingRule
{
    public const KEY = 'ebay1_volume_pricing';

    public const MAX_PCT = 80.0;

    /** Shipping Master bands in pounds, without the 0 lb slab. From/To can be edited. */
    private const DEFAULT_WEIGHT = [
        [0.01, 0.25],
        [0.25, 0.50],
        [0.50, 0.75],
        [0.75, 1],
        [1, 2],
        [2.01, 3],
        [3.01, 4],
        [4.01, 5],
        [5.01, 10],
        [10.01, 20],
        [20.01, 25],
        [25.01, 30],
        [30.01, 40],
        [40.01, 50],
        [50.01, 999],
    ];

    /** Older saves stored a shipping-master slab key instead of From/To. */
    private const LEGACY_WEIGHT_KEYS = [
        'oz_4' => [0.01, 0.25],
        'oz_6' => [0.25, 0.50],
        'oz_12' => [0.50, 0.75],
        'oz_1599' => [0.75, 1],
        'lb_101_2' => [1, 2],
        'lb_201_3' => [2.01, 3],
        'lb_301_4' => [3.01, 4],
        'lb_401_5' => [4.01, 5],
        'lb_501_10' => [5.01, 10],
        'lb_1001_20' => [10.01, 20],
        'lb_20_30' => [20.01, 25],
        'lb_2501_30' => [25.01, 30],
        'lb_30_40' => [30.01, 40],
        'lb_40_50' => [40.01, 50],
        'lb_gt50' => [50.01, 999],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $weight = [];
        foreach (self::DEFAULT_WEIGHT as [$min, $max]) {
            $weight[] = ['min' => $min, 'max' => $max, 'buy2' => 0.0, 'buy3' => 0.0, 'buy4' => 0.0];
        }

        return [
            'enabled' => false,
            'auto_push' => false,
            'weight' => $weight,
            'dil' => [
                ['min' => 0, 'max' => 0, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 0.01, 'max' => 10, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 10, 'max' => 25, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 25, 'max' => 50, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 50, 'max' => 100, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 100, 'max' => 9999, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
            ],
            'npft' => [
                ['min' => -999, 'max' => 0, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 0, 'max' => 10, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 10, 'max' => 20, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 20, 'max' => 30, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
                ['min' => 30, 'max' => 999, 'buy2' => 0, 'buy3' => 0, 'buy4' => 0],
            ],
            'promotion_ids' => [],
            'promotion_state' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $saved
     * @return array<string, mixed>
     */
    public static function merge(?array $saved): array
    {
        $base = self::defaults();
        if (! is_array($saved)) {
            return $base;
        }
        $clean = self::normalize($saved);
        if ($clean['weight'] === []) {
            $clean['weight'] = $base['weight'];
        }
        if ($clean['dil'] === []) {
            $clean['dil'] = $base['dil'];
        }
        if ($clean['npft'] === []) {
            $clean['npft'] = $base['npft'];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $weightRaw = $raw['weight'] ?? null;
        $weight = self::ranges($weightRaw);
        if ($weight === [] && is_array($weightRaw)) {
            $weight = self::legacyWeight($weightRaw);
        }

        $ids = [];
        foreach (is_array($raw['promotion_ids'] ?? null) ? $raw['promotion_ids'] : [] as $sig => $id) {
            $sig = trim((string) $sig);
            $id = trim((string) $id);
            if ($sig !== '' && $id !== '') {
                $ids[$sig] = $id;
            }
        }

        $state = [];
        foreach (is_array($raw['promotion_state'] ?? null) ? $raw['promotion_state'] : [] as $sig => $hash) {
            $sig = trim((string) $sig);
            $hash = trim((string) $hash);
            if ($sig !== '' && $hash !== '') {
                $state[$sig] = $hash;
            }
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'auto_push' => (bool) ($raw['auto_push'] ?? false),
            'weight' => $weight,
            'dil' => self::ranges($raw['dil'] ?? null),
            'npft' => self::ranges($raw['npft'] ?? null),
            'promotion_ids' => $ids,
            'promotion_state' => $state,
        ];
    }

    /**
     * One grid row as the push needs it. Same math as the Volume Pricing page (Dil = OV L30 / INV,
     * Std NPFT = ((Std x 0.70 - ship - LP) / Std) x 100). Parent rows and rows with no listing id give null.
     *
     * @param  array<string, mixed>  $d
     * @return array{sku:string,item_id:string,weight_lb:?float,dil:float,npft:?float}|null
     */
    public static function listingRow(array $d): ?array
    {
        $sku = trim((string) ($d['(Child) sku'] ?? ''));
        if (! empty($d['is_parent_summary']) || $sku === '' || stripos($sku, 'PARENT') === 0) {
            return null;
        }
        $itemId = trim((string) ($d['eBay_item_id'] ?? ''));
        if ($itemId === '') {
            return null;
        }
        $num = static fn ($v): float => is_numeric($v) && is_finite((float) $v) ? (float) $v : 0.0;
        $inv = $num($d['INV'] ?? 0);
        $ov = $num($d['L30'] ?? 0);
        $std = $num($d['STANDARD_PRICE'] ?? 0);
        $lp = $num($d['LP_productmaster'] ?? 0);
        $ship = $num($d['Ship_productmaster'] ?? 0);

        return [
            'sku' => $sku,
            'item_id' => $itemId,
            'weight_lb' => is_numeric($d['wt_act'] ?? null) ? (float) $d['wt_act'] : null,
            'dil' => $inv > 0 ? ($ov / $inv) * 100 : 0.0,
            'npft' => $std > 0 ? round((($std * 0.70 - $ship - ($lp > 0 ? $lp : 0)) / $std) * 10000) / 100 : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rules  Normalized rules
     * @return array{buy2:float,buy3:float,buy4:float,parts:array<string,array{buy2:float,buy3:float,buy4:float}>}
     */
    public static function sum(?float $weightLb, ?float $dil, ?float $npft, array $rules): array
    {
        $weight = self::rangeTier($weightLb, is_array($rules['weight'] ?? null) ? $rules['weight'] : []);
        $dilTier = self::rangeTier($dil, is_array($rules['dil'] ?? null) ? $rules['dil'] : []);
        $npftTier = self::rangeTier($npft, is_array($rules['npft'] ?? null) ? $rules['npft'] : []);

        return [
            'buy2' => round($weight['buy2'] + $dilTier['buy2'] + $npftTier['buy2'], 1),
            'buy3' => round($weight['buy3'] + $dilTier['buy3'] + $npftTier['buy3'], 1),
            'buy4' => round($weight['buy4'] + $dilTier['buy4'] + $npftTier['buy4'], 1),
            'parts' => [
                'weight' => $weight,
                'dil' => $dilTier,
                'npft' => $npftTier,
            ],
        ];
    }

    /**
     * Tiers eBay will accept: consecutive from Buy 2 (stops at the first 0), later tiers stay strictly above earlier ones, cap 80.
     *
     * @param  array{buy2:float,buy3:float,buy4:float}  $sum
     * @return list<array{qty:int,percent:float}>
     */
    public static function ebayTiers(array $sum): array
    {
        $raw = [
            2 => self::cap((float) ($sum['buy2'] ?? 0)),
            3 => self::cap((float) ($sum['buy3'] ?? 0)),
            4 => self::cap((float) ($sum['buy4'] ?? 0)),
        ];
        $out = [];
        $prev = 0.0;
        foreach ($raw as $qty => $pct) {
            // eBay quantities must be consecutive from Buy 2 (Buy 1 is added at 0%), so stop at the first gap.
            if ($pct <= 0) {
                break;
            }
            if ($prev > 0 && $pct <= $prev) {
                $pct = round($prev + 0.1, 1);
            }
            if ($pct > self::MAX_PCT) {
                break;
            }
            $out[] = ['qty' => $qty, 'percent' => $pct];
            $prev = $pct;
        }

        return $out;
    }

    public static function signature(array $tiers): string
    {
        $parts = [];
        foreach ($tiers as $tier) {
            $parts[] = $tier['qty'].'='.self::numKey((float) $tier['percent']);
        }

        return implode('|', $parts);
    }

    /**
     * @param  list<array{min:float,max:float,buy2:float,buy3:float,buy4:float}>  $ranges
     * @return array{buy2:float,buy3:float,buy4:float}
     */
    public static function rangeTier(?float $value, array $ranges): array
    {
        $zero = ['buy2' => 0.0, 'buy3' => 0.0, 'buy4' => 0.0];
        if ($value === null || ! is_finite($value) || $ranges === []) {
            return $zero;
        }
        $ranges = array_values($ranges);
        $prevMax = null;
        foreach ($ranges as $range) {
            if (self::contains($value, $range, $prevMax)) {
                return self::tier($range);
            }
            $prevMax = (float) $range['max'];
        }
        $last = $ranges[count($ranges) - 1];

        return $value > (float) $last['max'] ? self::tier($last) : $zero;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{buy2:float,buy3:float,buy4:float}
     */
    public static function tier(array $row): array
    {
        return [
            'buy2' => self::pct($row['buy2'] ?? 0),
            'buy3' => self::pct($row['buy3'] ?? 0),
            'buy4' => self::pct($row['buy4'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{min:float,max:float,buy2:float,buy3:float,buy4:float}>
     */
    private static function legacyWeight(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! isset(self::LEGACY_WEIGHT_KEYS[$key])) {
                continue;
            }
            $byKey[$key] = $row;
        }
        $out = [];
        foreach (self::LEGACY_WEIGHT_KEYS as $key => [$min, $max]) {
            if (! isset($byKey[$key])) {
                continue;
            }
            $tier = self::tier($byKey[$key]);
            $out[] = [
                'min' => (float) $min,
                'max' => (float) $max,
                'buy2' => $tier['buy2'],
                'buy3' => $tier['buy3'],
                'buy4' => $tier['buy4'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{min:float,max:float,buy2:float,buy3:float,buy4:float}>
     */
    private static function ranges($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row) || ! is_numeric($row['min'] ?? null) || ! is_numeric($row['max'] ?? null)) {
                continue;
            }
            $min = round((float) $row['min'], 2);
            $max = round((float) $row['max'], 2);
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $tier = self::tier($row);
            $out[] = [
                'min' => $min,
                'max' => $max,
                'buy2' => $tier['buy2'],
                'buy3' => $tier['buy3'],
                'buy4' => $tier['buy4'],
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array{min:float,max:float}  $range
     */
    private static function contains(float $value, array $range, ?float $prevMax): bool
    {
        $min = (float) $range['min'];
        $max = (float) $range['max'];
        if (abs($min) < 0.0000001 && abs($max) < 0.0000001) {
            return abs($value) < 0.0000001;
        }
        $sharesEdge = $prevMax !== null && abs($min - $prevMax) < 0.0001;
        $loOk = $sharesEdge ? $value > $min : $value >= $min;

        return $loOk && $value <= $max;
    }

    private static function pct($value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }
        $n = round((float) $value, 1);
        if ($n > 100) {
            $n = 100.0;
        }
        if ($n < -100) {
            $n = -100.0;
        }

        return $n;
    }

    private static function cap(float $pct): float
    {
        if ($pct <= 0) {
            return 0.0;
        }

        return min(self::MAX_PCT, round($pct, 1));
    }

    private static function numKey(float $pct): string
    {
        $text = number_format($pct, 1, '.', '');

        return str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;
    }
}
