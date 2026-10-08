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
 * Four more range tables each add their own S Bid to the Dil bid:
 * Views, CVR %, eBay Sold (L30) and Std NPFT % (as on /lmp-overall).
 * S Bid = Dil + Views + CVR + eBay Sold + Std NPFT %.
 * CVR overlay then adjusts that sum the same way Sprc Dil adjusts Target NROI:
 * down arrow and CVR below the threshold, or up arrow and CVR above it.
 */
final class DilVsSbidRule
{
    public const KEY_EBAY1 = 'ebay1_dil_sbid';

    /** Extra range tables whose S Bid is added to the Dil slab bid. */
    public const TABLE_KEYS = ['views', 'cvr', 'sold', 'npft'];

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
            'tables' => self::normalizeTables(is_array($decoded) ? ($decoded['tables'] ?? null) : null),
        ];
    }

    /**
     * Ranges for Views, CVR %, eBay Sold and Std NPFT %. Every S Bid starts at 0
     * so nothing changes for an account until someone types a value.
     *
     * @return array<string, list<array{min:float,max:float,bid:float}>>
     */
    public static function defaultTables(): array
    {
        $build = function (array $edges): array {
            return array_map(fn (array $e) => ['min' => (float) $e[0], 'max' => (float) $e[1], 'bid' => 0.0], $edges);
        };

        return [
            'views' => $build([[0, 0], [0, 50], [50, 100], [100, 250], [250, 500], [500, 9999]]),
            'cvr' => $build([[0, 0], [0, 2], [2, 4], [4, 7], [7, 10], [10, 9999]]),
            'sold' => $build([[0, 0], [1, 2], [3, 5], [6, 10], [11, 25], [26, 9999]]),
            'npft' => $build([[-9999, 0], [0, 10], [10, 20], [20, 30], [30, 9999]]),
        ];
    }

    /**
     * Keeps valid rows only. A missing table falls back to its defaults.
     * A saved table with every row removed stays empty (adds nothing).
     *
     * @return array<string, list<array{min:float,max:float,bid:float}>>
     */
    public static function normalizeTables($raw): array
    {
        $out = self::defaultTables();
        if (! is_array($raw)) {
            return $out;
        }
        foreach (self::TABLE_KEYS as $key) {
            if (! isset($raw[$key]) || ! is_array($raw[$key])) {
                continue;
            }
            $rows = [];
            foreach (array_values($raw[$key]) as $slab) {
                if (! is_array($slab)) {
                    continue;
                }
                $min = self::num($slab['min'] ?? null);
                $max = self::num($slab['max'] ?? null);
                if ($min === null || $max === null || $max < $min) {
                    continue;
                }
                $bid = self::num($slab['bid'] ?? null) ?? 0.0;
                $rows[] = ['min' => $min, 'max' => $max, 'bid' => round($bid, 2)];
            }
            $out[$key] = $rows;
        }

        return $out;
    }

    /** True when the Std NPFT % table adds anything, so Std Price / LP / ship are worth loading. */
    public static function usesNpft(array $tables): bool
    {
        foreach ($tables['npft'] ?? [] as $slab) {
            if (abs((float) ($slab['bid'] ?? 0)) > 0.0000001) {
                return true;
            }
        }

        return false;
    }

    /**
     * Std NPFT % as on /lmp-overall: ((Std × 0.70 − ship − LP) / Std) × 100, 2 decimals.
     * Null when there is no Std Price.
     */
    public static function stdNpft(?float $std, ?float $lp, float $ship): ?float
    {
        if ($std === null || $std <= 0) {
            return null;
        }
        $lpVal = ($lp !== null && $lp > 0) ? $lp : 0.0;

        return round((($std * 0.70 - $ship - $lpVal) / $std) * 100, 2);
    }

    /**
     * S Bid of the first range holding $value. The last range is open at the top.
     * A range that starts where the previous one ended is exclusive on From.
     *
     * @param  list<array{min:float,max:float,bid:float}>  $slabs
     */
    public static function tableBid(?float $value, array $slabs): float
    {
        if ($value === null || ! is_finite($value) || $slabs === []) {
            return 0.0;
        }
        $slabs = array_values($slabs);
        $prevMax = null;
        foreach ($slabs as $slab) {
            if (self::contains($value, $slab, $prevMax)) {
                return (float) $slab['bid'];
            }
            $prevMax = (float) $slab['max'];
        }
        $last = $slabs[count($slabs) - 1];

        return $value > (float) $last['max'] ? (float) $last['bid'] : 0.0;
    }

    /**
     * Dil slab bid plus the four range tables.
     * With every extra table at 0 this is the same decision resolve() gives.
     *
     * @param  array<string, list<array{min:float,max:float,bid:float}>>  $tables  Normalized tables
     * @param  array{views?:?float,cvr?:?float,sold?:?float,npft?:?float}  $inputs
     * @return array{mode:string,bid:float,off:bool,label:string,parts:array<string,float>}
     */
    public static function resolveTotal(float $dil, float $esBid, array $slabs, array $tables, array $inputs): array
    {
        $decision = self::resolve($dil, $esBid, $slabs);
        $parts = ['dil' => (float) $decision['bid']];
        $extra = 0.0;
        foreach (self::TABLE_KEYS as $key) {
            $value = $inputs[$key] ?? null;
            $bid = self::tableBid(is_numeric($value) ? (float) $value : null, $tables[$key] ?? []);
            $parts[$key] = $bid;
            $extra += $bid;
        }
        $decision['parts'] = $parts;
        if (abs($extra) < 0.0000001) {
            return $decision;
        }

        $sum = round($parts['dil'] + $extra, 2);
        if ($sum <= 0) {
            $decision['mode'] = 'none';
            $decision['bid'] = 0.0;
            $decision['label'] = $decision['label'] !== '' ? $decision['label'] : 'No S Bid';

            return $decision;
        }

        $decision['mode'] = 'dynamic';
        $decision['bid'] = $sum;
        $decision['label'] = 'S Bid sum';

        return $decision;
    }

    /** Off until this account turns the switch on. Off uses View VS SBID. */
    public static function isEnabled(?array $decoded): bool
    {
        return (bool) ($decoded['enabled'] ?? false);
    }

    /**
     * Down: CVR below the threshold and a down arrow (CVR L30 under CVR L60).
     * Up: CVR above the threshold and an up arrow. Adj is added to the slab S Bid.
     * down_more / up_more hold extra ranges. With several Down ranges the lowest
     * threshold that CVR is under wins. With several Up ranges the highest
     * threshold that CVR is over wins.
     *
     * @return array{down_lt:float,down_adj:float,up_gt:float,up_adj:float,down_more:list<array{lt:float,adj:float}>,up_more:list<array{gt:float,adj:float}>}
     */
    public static function defaultCvr(): array
    {
        return ['down_lt' => 7.0, 'down_adj' => -10.0, 'up_gt' => 10.0, 'up_adj' => 10.0, 'down_more' => [], 'up_more' => []];
    }

    /**
     * @return array{down_lt:float,down_adj:float,up_gt:float,up_adj:float,down_more:list<array{lt:float,adj:float}>,up_more:list<array{gt:float,adj:float}>}
     */
    public static function normalizeCvr($raw): array
    {
        $out = self::defaultCvr();
        if (! is_array($raw)) {
            return $out;
        }
        foreach (['down_more' => 'lt', 'up_more' => 'gt'] as $listKey => $edgeKey) {
            if (! isset($raw[$listKey]) || ! is_array($raw[$listKey])) {
                continue;
            }
            foreach (array_values($raw[$listKey]) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $edge = self::num($item[$edgeKey] ?? null);
                $adj = self::num($item['adj'] ?? null);
                if ($edge === null || $edge < 0 || $adj === null) {
                    continue;
                }
                $out[$listKey][] = [$edgeKey => $edge, 'adj' => $adj];
            }
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
        if ($trend === 'down') {
            $rules = [['lt' => (float) $cvr['down_lt'], 'adj' => (float) $cvr['down_adj']]];
            foreach ($cvr['down_more'] as $more) {
                $rules[] = ['lt' => (float) $more['lt'], 'adj' => (float) $more['adj']];
            }
            usort($rules, fn ($a, $b) => $a['lt'] <=> $b['lt']);
            foreach ($rules as $rule) {
                if ($cvr30 < $rule['lt']) {
                    $adj = $rule['adj'];
                    $why = 'CVR Down < '.$rule['lt'].'% and down arrow';
                    break;
                }
            }
        } elseif ($trend === 'up') {
            $rules = [['gt' => (float) $cvr['up_gt'], 'adj' => (float) $cvr['up_adj']]];
            foreach ($cvr['up_more'] as $more) {
                $rules[] = ['gt' => (float) $more['gt'], 'adj' => (float) $more['adj']];
            }
            usort($rules, fn ($a, $b) => $b['gt'] <=> $a['gt']);
            foreach ($rules as $rule) {
                if ($cvr30 > $rule['gt']) {
                    $adj = $rule['adj'];
                    $why = 'CVR Up > '.$rule['gt'].'% and up arrow';
                    break;
                }
            }
        }

        $next = round($bid + $adj, 2);

        return ['bid' => $next < 0 ? 0.0 : $next, 'adj' => $adj, 'why' => $why];
    }

    /**
     * @return array{success:bool, enabled?:bool, slabs?:array, cvr?:array, tables?:array, error?:string}
     */
    public static function save(string $key, $slabs, $enabled = null, $cvr = null, $tables = null): array
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
        $tablesClean = $tables === null ? $stored['tables'] : self::normalizeTables($tables);

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => $key],
            ['rule' => json_encode(['enabled' => $on, 'slabs' => $clean, 'cvr' => $cvrClean, 'tables' => $tablesClean]), 'updated_at' => now()]
        );

        return ['success' => true, 'enabled' => $on, 'slabs' => $clean, 'cvr' => $cvrClean, 'tables' => $tablesClean];
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
