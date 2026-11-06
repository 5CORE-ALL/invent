<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ads Rule PMT (eBay 1): per-category Highest / Lowest bid % plus the On / Off switch.
 *
 * Rules, first match wins:
 *   1. INV = 0                          -> bid 0 and the ad is paused
 *   2. eBay sold (L30) = 0              -> Highest + 1
 *   3. Dil > 25%  and eBay sold > 0     -> Lowest
 *   4. Dil > 50%  and eBay sold > 0     -> Lowest - 1
 *   5. Dil > 100% and eBay sold > 0     -> 2
 *   (sold > 0 and Dil <= 25%)           -> Highest
 * Higher Dil rules override lower ones (5 over 4 over 3).
 *
 * The same logic runs in the browser (ebay_tabulator_view) for the PMT BID column.
 */
final class PmtCategoryBidRule
{
    public const KEY_BIDS = 'ebay1_pmt_category_bids';

    public const KEY_SWITCH = 'ebay1_pmt_rule';

    public const ACCOUNTS = ['ebay1', 'ebay2', 'ebay3'];

    /** eBay 1 keeps its original keys; eBay 2 / eBay 3 use ebay2_... / ebay3_... */
    public static function keyBids(string $account = 'ebay1'): string
    {
        return $account === 'ebay1' ? self::KEY_BIDS : $account.'_pmt_category_bids';
    }

    public static function keySwitch(string $account = 'ebay1'): string
    {
        return $account === 'ebay1' ? self::KEY_SWITCH : $account.'_pmt_rule';
    }

    public static function isOn(string $account = 'ebay1'): bool
    {
        $row = DB::table('ebay_sbid_rules')->where('key', self::keySwitch($account))->first();
        $decoded = $row ? json_decode((string) $row->rule, true) : null;

        return (bool) (is_array($decoded) ? ($decoded['enabled'] ?? false) : false);
    }

    public static function setEnabled(bool $on, string $account = 'ebay1'): void
    {
        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => self::keySwitch($account)],
            ['rule' => json_encode(['enabled' => $on]), 'updated_at' => now()]
        );
    }

    /**
     * @return array<string, array{highest:?float,lowest:?float}>
     */
    public static function bids(string $account = 'ebay1'): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', self::keyBids($account))->first();
        $stored = $row ? (json_decode((string) $row->rule, true) ?: []) : [];

        $out = [];
        foreach ($stored as $category => $value) {
            if (is_array($value)) {
                $hi = $value['highest'] ?? null;
                $lo = $value['lowest'] ?? null;
            } else { // older data: a plain number = highest
                $hi = $value;
                $lo = null;
            }
            $out[(string) $category] = [
                'highest' => is_numeric($hi) ? (float) $hi : null,
                'lowest' => is_numeric($lo) ? (float) $lo : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{rule:string,pause:bool,bid:?float,label:string}
     */
    public static function decide(float $inv, float $dil, float $sold, ?float $highest, ?float $lowest): array
    {
        if ($inv <= 0) {
            return ['rule' => '1', 'pause' => true, 'bid' => null, 'label' => 'PMT rule 1: INV 0, ad paused'];
        }
        if ($sold <= 0) {
            return self::fromBound('2', 'Highest + 1', $highest, 1.0);
        }
        if ($dil > 100) {
            return ['rule' => '5', 'pause' => false, 'bid' => 2.0, 'label' => 'PMT rule 5: Dil > 100%, bid 2'];
        }
        if ($dil > 50) {
            return self::fromBound('4', 'Lowest - 1', $lowest, -1.0);
        }
        if ($dil > 25) {
            return self::fromBound('3', 'Lowest', $lowest, 0.0);
        }

        return self::fromBound('base', 'Highest', $highest, 0.0);
    }

    /**
     * @return array{rule:string,pause:bool,bid:?float,label:string}
     */
    private static function fromBound(string $rule, string $what, ?float $bound, float $offset): array
    {
        $name = str_starts_with($what, 'Highest') ? 'Highest' : 'Lowest';
        if ($bound === null) {
            return ['rule' => $rule, 'pause' => false, 'bid' => null, 'label' => "PMT rule {$rule}: no {$name} set for this category"];
        }

        return [
            'rule' => $rule,
            'pause' => false,
            'bid' => max(0.0, round($bound + $offset, 2)),
            'label' => "PMT rule {$rule}: {$what}",
        ];
    }
}
