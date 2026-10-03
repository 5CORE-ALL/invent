<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dil → S Bid slabs for one eBay campaign-ads account.
 * Each account has its own key in ebay_sbid_rules (not shared).
 *
 * Row 1 (0–0): use that listing's ES Bid.
 * Later rows: the editable S Bid % on that slab.
 * First matching slab wins. A slab that starts where the previous one ended
 * is exclusive on From, so Dil 10 stays on 0.1–10.
 * CVR overlay then adjusts that bid the same way Sprc Dil adjusts Target NROI:
 * down arrow and CVR below the threshold, or up arrow and CVR above it.
 */
final class DilVsSbidRule
{
    public const KEY_EBAY1 = 'ebay1_dil_sbid';

    public const KEY_EBAY2 = 'ebay2_dil_sbid';

    public const KEY_EBAY3 = 'ebay3_dil_sbid';

    public static function defaultSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 0, 'mode' => 'es_bid', 'bid' => null],
            ['min' => 0.1, 'max' => 10, 'mode' => 'dynamic', 'bid' => 8],
        ];
    }

    public static function load(string $key): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', $key)->first();
        $decoded = $row ? json_decode((string) $row->rule, true) : null;
        $raw = is_array($decoded['slabs'] ?? null) ? $decoded['slabs'] : self::defaultSlabs();

        return [
            'enabled' => self::isEnabled(is_array($decoded) ? $decoded : null),
            'slabs' => self::normalize($raw),
            'cvr' => self::normalizeCvr(is_array($decoded) ? ($decoded['cvr'] ?? null) : null),
        ];
    }

    /** Off until this account turns the switch on. Off uses View VS SBID. */
    public static function isEnabled(?array $decoded): bool
    {
        return (bool) ($decoded['enabled'] ?? false);
    }

    /**
     * Down: CVR below the threshold and a down arrow (CVR L30 under CVR L60).
     * Up: CVR above the threshold and an up arrow. Adj is added to the slab S Bid.
     *
     * @return array{down_lt:float,down_adj:float,up_gt:float,up_adj:float}
     */
    public static function defaultCvr(): array
    {
        return ['down_lt' => 7.0, 'down_adj' => -10.0, 'up_gt' => 10.0, 'up_adj' => 10.0];
    }

    /**
     * @return array{down_lt:float,down_adj:float,up_gt:float,up_adj:float}
     */
    public static function normalizeCvr($raw): array
    {
        $out = self::defaultCvr();
        if (! is_array($raw)) {
            return $out;
        }
        $downLt = self::num($raw['down_lt'] ?? null);
        $downAdj = self::num($raw['down_adj'] ?? null);
        $upGt = self::num($raw['up_gt'] ?? null);
        $upAdj = self::num($raw['up_adj'] ?? null);
        if ($downLt !== null && $downLt >= 0) {
            $out['down_lt'] = $downLt;
        }
        if ($downAdj !== null) {
            $out['down_adj'] = $downAdj;
        }
        if ($upGt !== null && $upGt >= 0) {
            $out['up_gt'] = $upGt;
        }
        if ($upAdj !== null) {
            $out['up_adj'] = $upAdj;
        }

        return $out;
    }

    /**
     * @param  array{down_lt?:float,down_adj?:float,up_gt?:float,up_adj?:float}  $cvr
     * @return array{bid:float,adj:float,why:string}
     */
    public static function applyCvr(float $bid, float $views, float $l30, float $l60, array $cvr): array
    {
        $cvr = self::normalizeCvr($cvr);
        if ($bid <= 0 || $views <= 0) {
            return ['bid' => $bid, 'adj' => 0.0, 'why' => ''];
        }

        $cvr30 = ($l30 / $views) * 100;
        $cvr60 = ($l60 / $views) * 100;
        $tol = 0.1;
        $trend = ($cvr30 == 0.0 || $cvr30 < $cvr60 - $tol) ? 'down' : (($cvr30 > $cvr60 + $tol) ? 'up' : 'flat');
        $adj = 0.0;
        $why = '';
        if ($trend === 'down' && $cvr30 < (float) $cvr['down_lt']) {
            $adj = (float) $cvr['down_adj'];
            $why = 'CVR Down < '.$cvr['down_lt'].'% and down arrow';
        } elseif ($trend === 'up' && $cvr30 > (float) $cvr['up_gt']) {
            $adj = (float) $cvr['up_adj'];
            $why = 'CVR Up > '.$cvr['up_gt'].'% and up arrow';
        }

        $next = round($bid + $adj, 2);

        return ['bid' => $next < 0 ? 0.0 : $next, 'adj' => $adj, 'why' => $why];
    }

    /**
     * @return array{success:bool, enabled?:bool, slabs?:array, cvr?:array, error?:string}
     */
    public static function save(string $key, $slabs, $enabled = null, $cvr = null): array
    {
        if (! is_array($slabs) || $slabs === []) {
            return ['success' => false, 'error' => 'Add at least one Dil slab'];
        }

        $clean = self::normalize($slabs);
        if ($clean === []) {
            return ['success' => false, 'error' => 'Invalid Dil slabs'];
        }

        $stored = self::load($key);
        $on = $enabled === null ? $stored['enabled'] : (bool) $enabled;
        $cvrClean = $cvr === null ? $stored['cvr'] : self::normalizeCvr($cvr);

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => $key],
            ['rule' => json_encode(['enabled' => $on, 'slabs' => $clean, 'cvr' => $cvrClean]), 'updated_at' => now()]
        );

        return ['success' => true, 'enabled' => $on, 'slabs' => $clean, 'cvr' => $cvrClean];
    }

    public static function normalize(array $slabs): array
    {
        $clean = [];
        foreach (array_values($slabs) as $slab) {
            if (! is_array($slab) || ($slab['mode'] ?? '') === 'auto_off') {
                continue;
            }
            $min = self::num($slab['min'] ?? null);
            $max = self::num($slab['max'] ?? null);
            if ($min === null || $max === null || $max < $min) {
                continue;
            }
            $mode = self::modeForIndex(count($clean));
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

        return $clean;
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

        $last = $slabs[array_key_last($slabs)];
        if ($dil > (float) $last['max']) {
            return self::decision($last, $esBid);
        }

        return ['mode' => 'none', 'bid' => 0.0, 'off' => false, 'label' => ''];
    }

    public static function modeForIndex(int $index): string
    {
        if ($index <= 0) {
            return 'es_bid';
        }

        return 'dynamic';
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
