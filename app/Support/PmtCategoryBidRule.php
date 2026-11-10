<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ads Rule PMT: per-category Highest / Lowest bid % and Promoted ad rate Manual, plus the On / Off switch.
 *
 * Rules, first match wins:
 *   1. INV = 0                              -> bid 0 and the ad is paused
 *   2. the category has a Manual rate       -> Manual
 *   3. the category has a Highest           -> Highest
 *   4. no match (category has neither)      -> the old Dil vs SBid S Bid of the listing (eBay 1 only;
 *                                              the other accounts skip the listing, as before)
 * Lowest is still stored and shown but no longer pushes a bid.
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
     * @return array<string, array{highest:?float,lowest:?float,manual:?float}>
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
                $man = $value['manual'] ?? null;
            } else { // older data: a plain number = highest
                $hi = $value;
                $lo = null;
                $man = null;
            }
            $out[(string) $category] = [
                'highest' => is_numeric($hi) ? (float) $hi : null,
                'lowest' => is_numeric($lo) ? (float) $lo : null,
                'manual' => is_numeric($man) ? (float) $man : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{rule:string,pause:bool,bid:?float,label:string,fallback:bool}
     *         fallback = no match for the category: the caller uses the old Dil vs SBid bid
     */
    public static function decide(float $inv, ?float $highest, ?float $manual = null): array
    {
        if ($inv <= 0) {
            return ['rule' => '1', 'pause' => true, 'bid' => null, 'label' => 'PMT rule 1: INV 0, ad paused', 'fallback' => false];
        }
        if ($manual !== null) {
            return ['rule' => '2', 'pause' => false, 'bid' => max(0.0, round($manual, 2)), 'label' => 'PMT rule 2: category Promoted ad rate Manual', 'fallback' => false];
        }
        if ($highest !== null) {
            return ['rule' => '3', 'pause' => false, 'bid' => max(0.0, round($highest, 2)), 'label' => 'PMT rule 3: category Highest', 'fallback' => false];
        }

        return ['rule' => '4', 'pause' => false, 'bid' => null, 'label' => 'PMT rule 4: no Highest for this category, old S Bid', 'fallback' => true];
    }
}
