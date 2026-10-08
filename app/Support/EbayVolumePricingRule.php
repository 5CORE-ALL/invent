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

    /**
     * @param  list<array{key:string,label:string}>  $slabs
     * @return array<string, mixed>
     */
    public static function defaults(array $slabs): array
    {
        $weight = [];
        foreach ($slabs as $slab) {
            $key = trim((string) ($slab['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $weight[] = [
                'key' => $key,
                'label' => (string) ($slab['label'] ?? $key),
                'buy2' => 0.0,
                'buy3' => 0.0,
                'buy4' => 0.0,
            ];
        }

        return [
            'enabled' => false,
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
        ];
    }

    /**
     * Keep saved percents, and add any new shipping-master slabs at 0.
     *
     * @param  array<string, mixed>|null  $saved
     * @param  list<array{key:string,label:string}>  $slabs
     * @return array<string, mixed>
     */
    public static function merge(?array $saved, array $slabs): array
    {
        $base = self::defaults($slabs);
        if (! is_array($saved)) {
            return $base;
        }
        $clean = self::normalize($saved, $slabs);
        $byKey = [];
        foreach ($clean['weight'] as $row) {
            $byKey[$row['key']] = $row;
        }
        $weight = [];
        foreach ($base['weight'] as $row) {
            $weight[] = $byKey[$row['key']] ?? $row;
            $weight[count($weight) - 1]['label'] = $row['label'];
        }
        $clean['weight'] = $weight;
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
     * @param  list<array{key:string,label:string}>  $slabs
     * @return array<string, mixed>
     */
    public static function normalize(array $raw, array $slabs): array
    {
        $labels = [];
        foreach ($slabs as $slab) {
            $key = trim((string) ($slab['key'] ?? ''));
            if ($key !== '') {
                $labels[$key] = (string) ($slab['label'] ?? $key);
            }
        }
        $weight = [];
        foreach (is_array($raw['weight'] ?? null) ? $raw['weight'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! isset($labels[$key]) || isset($weight[$key])) {
                continue;
            }
            $tier = self::tier($row);
            $weight[$key] = [
                'key' => $key,
                'label' => $labels[$key],
                'buy2' => $tier['buy2'],
                'buy3' => $tier['buy3'],
                'buy4' => $tier['buy4'],
            ];
        }

        $ids = [];
        foreach (is_array($raw['promotion_ids'] ?? null) ? $raw['promotion_ids'] : [] as $sig => $id) {
            $sig = trim((string) $sig);
            $id = trim((string) $id);
            if ($sig !== '' && $id !== '') {
                $ids[$sig] = $id;
            }
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'weight' => array_values($weight),
            'dil' => self::ranges($raw['dil'] ?? null),
            'npft' => self::ranges($raw['npft'] ?? null),
            'promotion_ids' => $ids,
        ];
    }

    /**
     * @param  array<string, mixed>  $rules  Normalized rules
     * @return array{buy2:float,buy3:float,buy4:float,parts:array<string,array{buy2:float,buy3:float,buy4:float}>}
     */
    public static function sum(string $slabKey, ?float $dil, ?float $npft, array $rules): array
    {
        $weight = ['buy2' => 0.0, 'buy3' => 0.0, 'buy4' => 0.0];
        foreach ($rules['weight'] ?? [] as $row) {
            if (is_array($row) && (string) ($row['key'] ?? '') === $slabKey) {
                $weight = self::tier($row);
                break;
            }
        }
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
     * Tiers eBay will accept: 0 is omitted, later tiers stay strictly above earlier ones, cap 80.
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
            if ($pct <= 0) {
                continue;
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
