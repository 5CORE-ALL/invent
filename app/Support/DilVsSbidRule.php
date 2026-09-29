<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dil → S Bid slabs for one eBay campaign-ads account.
 * Each account has its own key in ebay_sbid_rules (not shared).
 *
 * Row 1 (0–0): use that listing's ES Bid.
 * Row 2 (0.1–10): the editable S Bid %.
 * Later rows (10–20 … >100): Auto Off (pause the promoted listing).
 * First matching slab wins. A slab that starts where the previous one ended
 * is exclusive on From, so Dil 10 stays on 0.1–10 and Dil 10.01 is Auto Off.
 */
final class DilVsSbidRule
{
    public const KEY_EBAY1 = 'ebay1_dil_sbid';

    public const KEY_EBAY2 = 'ebay2_dil_sbid';

    public const KEY_EBAY3 = 'ebay3_dil_sbid';

    public static function defaultSlabs(): array
    {
        $slabs = [
            ['min' => 0, 'max' => 0, 'mode' => 'es_bid', 'bid' => null],
            ['min' => 0.1, 'max' => 10, 'mode' => 'dynamic', 'bid' => 8],
        ];
        for ($from = 10; $from < 100; $from += 10) {
            $slabs[] = [
                'min' => $from,
                'max' => $from + 10,
                'mode' => 'auto_off',
                'bid' => null,
            ];
        }
        $slabs[] = ['min' => 100, 'max' => 9999, 'mode' => 'auto_off', 'bid' => null];

        return $slabs;
    }

    public static function load(string $key): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', $key)->first();
        $decoded = $row ? json_decode((string) $row->rule, true) : null;
        $raw = is_array($decoded['slabs'] ?? null) ? $decoded['slabs'] : self::defaultSlabs();

        return [
            'enabled' => self::isEnabled(is_array($decoded) ? $decoded : null),
            'slabs' => self::normalize($raw),
        ];
    }

    /** Off until this account turns the switch on. Off uses View VS SBID. */
    public static function isEnabled(?array $decoded): bool
    {
        return (bool) ($decoded['enabled'] ?? false);
    }

    /**
     * @return array{success:bool, enabled?:bool, slabs?:array, error?:string}
     */
    public static function save(string $key, $slabs, $enabled = null): array
    {
        if (! is_array($slabs) || $slabs === []) {
            return ['success' => false, 'error' => 'Add at least one Dil slab'];
        }

        $clean = self::normalize($slabs);
        if ($clean === []) {
            return ['success' => false, 'error' => 'Invalid Dil slabs'];
        }

        $on = $enabled === null
            ? self::load($key)['enabled']
            : (bool) $enabled;

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => $key],
            ['rule' => json_encode(['enabled' => $on, 'slabs' => $clean]), 'updated_at' => now()]
        );

        return ['success' => true, 'enabled' => $on, 'slabs' => $clean];
    }

    public static function normalize(array $slabs): array
    {
        $clean = [];
        foreach (array_values($slabs) as $i => $slab) {
            if (! is_array($slab)) {
                continue;
            }
            $min = self::num($slab['min'] ?? null);
            $max = self::num($slab['max'] ?? null);
            if ($min === null || $max === null || $max < $min) {
                continue;
            }
            $mode = self::modeForIndex($i);
            $bid = null;
            if ($mode === 'dynamic') {
                $bid = self::num($slab['bid'] ?? null);
                if ($bid === null || $bid < 0) {
                    $bid = 8.0;
                }
            }
            $clean[] = [
                'min' => $min,
                'max' => $max,
                'mode' => $mode,
                'bid' => $bid,
            ];
        }

        return self::extendAutoOff($clean);
    }

    /**
     * Auto Off runs from the first pause slab through Dil above 100.
     * A saved rule that stops at 10–20 still pauses every higher Dil.
     *
     * @param  array<int, array<string, mixed>>  $slabs
     * @return array<int, array<string, mixed>>
     */
    private static function extendAutoOff(array $slabs): array
    {
        if ($slabs === []) {
            return $slabs;
        }
        $last = $slabs[count($slabs) - 1];
        if (($last['mode'] ?? '') !== 'auto_off') {
            return $slabs;
        }
        $from = (float) $last['max'];
        if ($from >= 9999) {
            return $slabs;
        }
        while ($from < 100) {
            $to = $from + 10;
            $slabs[] = ['min' => $from, 'max' => $to, 'mode' => 'auto_off', 'bid' => null];
            $from = $to;
        }
        if ($from < 9999) {
            $slabs[] = ['min' => $from, 'max' => 9999, 'mode' => 'auto_off', 'bid' => null];
        }

        return $slabs;
    }

    /**
     * @return array{mode:string,bid:float,off:bool,label:string}
     */
    public static function resolve(float $dil, float $esBid, array $slabs): array
    {
        $slabs = self::normalize($slabs);
        $prevMax = null;
        foreach ($slabs as $slab) {
            if (self::contains($dil, $slab, $prevMax)) {
                return self::decision($slab, $esBid);
            }
            $prevMax = (float) $slab['max'];
        }

        return ['mode' => 'none', 'bid' => 0.0, 'off' => false, 'label' => ''];
    }

    public static function modeForIndex(int $index): string
    {
        if ($index <= 0) {
            return 'es_bid';
        }
        if ($index === 1) {
            return 'dynamic';
        }

        return 'auto_off';
    }

    private static function contains(float $dil, array $slab, ?float $prevMax): bool
    {
        $min = (float) $slab['min'];
        $max = (float) $slab['max'];
        if (abs($min) < 0.0000001 && abs($max) < 0.0000001) {
            return abs($dil) < 0.0000001;
        }
        $sharesEdge = $prevMax !== null && abs($min - $prevMax) < 0.0001;
        $loOk = $sharesEdge ? $dil > $min : $dil >= $min;

        return $loOk && $dil <= $max;
    }

    /**
     * @return array{mode:string,bid:float,off:bool,label:string}
     */
    private static function decision(array $slab, float $esBid): array
    {
        $mode = (string) $slab['mode'];
        if ($mode === 'auto_off') {
            return ['mode' => 'auto_off', 'bid' => 0.0, 'off' => true, 'label' => 'Auto Off'];
        }
        if ($mode === 'es_bid') {
            if ($esBid > 0) {
                return ['mode' => 'es_bid', 'bid' => $esBid, 'off' => false, 'label' => 'ES Bid'];
            }

            return ['mode' => 'none', 'bid' => 0.0, 'off' => false, 'label' => 'ES Bid missing'];
        }

        $bid = (float) ($slab['bid'] ?? 0);
        if ($bid > 0) {
            return ['mode' => 'dynamic', 'bid' => $bid, 'off' => false, 'label' => 'S Bid'];
        }

        return ['mode' => 'none', 'bid' => 0.0, 'off' => false, 'label' => 'Set S Bid %'];
    }

    private static function num($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
