<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ads Rule PMT: per-category Highest / Lowest bid %, plus the On / Off switch.
 *
 * Rules, first match wins:
 *   1. INV = 0                              -> bid 0 and the ad is paused
 *   2. the category has a Highest           -> Highest
 *   3. no match                             -> the old Dil vs SBid S Bid of the listing (when enabled)
 * The old Promoted ad rate Manual is gone (the column is now the read-only live CBID): a stored value is ignored.
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
                'manual' => null, // retired: stored values are ignored
            ];
        }

        // eBay 2 saved its ranges under the /excel sheet names (cut at 31 characters). The categories are now the
        // Category Masters ones, so a saved name that is the start of a master category moves to that category.
        if ($account === 'ebay2' && $out !== []) {
            $out = self::renameToMasterCategories($out);
        }

        return $out;
    }

    /**
     * @param  array<string, array{highest:?float,lowest:?float,manual:?float}>  $bids
     * @return array<string, array{highest:?float,lowest:?float,manual:?float}>
     */
    private static function renameToMasterCategories(array $bids): array
    {
        try {
            $masters = app(\App\Services\ExcelCategoryLookupService::class)->masterCategoryNames();
        } catch (\Throwable $e) {
            return $bids;
        }
        if ($masters === []) {
            return $bids;
        }
        $isMaster = array_flip($masters);
        foreach (array_keys($bids) as $key) {
            if (isset($isMaster[$key])) {
                continue;
            }
            $k = mb_strtolower(trim($key));
            foreach ($masters as $name) {
                $n = mb_strtolower($name);
                if (min(mb_strlen($k), mb_strlen($n)) >= 10 && (str_starts_with($n, $k) || str_starts_with($k, $n)) && ! isset($bids[$name])) {
                    $bids[$name] = $bids[$key];
                    unset($bids[$key]);
                    break;
                }
            }
        }

        return $bids;
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
        if ($highest !== null) {
            return ['rule' => '2', 'pause' => false, 'bid' => max(0.0, round($highest, 2)), 'label' => 'PMT rule 2: category Highest', 'fallback' => false];
        }

        return ['rule' => '3', 'pause' => false, 'bid' => null, 'label' => 'PMT rule 3: no Highest for this category, old S Bid', 'fallback' => true];
    }
}
