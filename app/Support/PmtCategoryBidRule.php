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

    // ---- Pause conditions (dynamic): extra "IF DIL < 5 AND L30 < 2 ..." conditions that also pause the ad (rule 1) ----

    /** Columns a pause condition can use (DIL = OV L30 / INV x 100). */
    public const PAUSE_COLUMNS = ['dil', 'l30', 'inv'];

    public const PAUSE_OPERATORS = ['<', '<=', '=', '!=', '>=', '>'];

    public static function keyPause(string $account = 'ebay1'): string
    {
        return $account.'_pmt_pause_rule';
    }

    /**
     * Clean and validate a pause rule from the browser.
     *
     * @param  mixed  $raw  {enabled: bool, conditions: [{join:'AND'|'OR', column, op, mode:'number'|'column', value}]}
     * @return array{enabled:bool,conditions:array<int,array{join:string,column:string,op:string,mode:string,value:string}>}
     *
     * @throws \InvalidArgumentException
     */
    public static function normalizePauseRule($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $conditions = [];
        foreach ((array) ($raw['conditions'] ?? []) as $c) {
            if (! is_array($c)) {
                continue;
            }
            $column = (string) ($c['column'] ?? '');
            $op = (string) ($c['op'] ?? '');
            $mode = ($c['mode'] ?? 'number') === 'column' ? 'column' : 'number';
            $value = trim((string) ($c['value'] ?? ''));
            if (! in_array($column, self::PAUSE_COLUMNS, true)) {
                throw new \InvalidArgumentException('Pause rule: unknown column "'.$column.'".');
            }
            if (! in_array($op, self::PAUSE_OPERATORS, true)) {
                throw new \InvalidArgumentException('Pause rule: unknown condition "'.$op.'".');
            }
            if ($mode === 'column') {
                if (! in_array($value, self::PAUSE_COLUMNS, true)) {
                    continue; // nothing to compare with: ignored, same as an empty number
                }
            } elseif ($value !== '' && ! is_numeric($value)) {
                throw new \InvalidArgumentException('Pause rule: "'.$value.'" is not a number.');
            }
            // An empty number is kept (the row stays in the editor) but never counts when the rule runs.
            $conditions[] = [
                'join' => ($c['join'] ?? 'AND') === 'OR' ? 'OR' : 'AND',
                'column' => $column,
                'op' => $op,
                'mode' => $mode,
                'value' => $value,
            ];
        }
        if (count($conditions) > 20) {
            throw new \InvalidArgumentException('Pause rule: at most 20 conditions.');
        }

        return ['enabled' => (bool) ($raw['enabled'] ?? false), 'conditions' => $conditions];
    }

    /** @return array{enabled:bool,conditions:array<int,array<string,string>>} */
    public static function pauseRule(string $account = 'ebay1'): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', self::keyPause($account))->first();
        $stored = $row ? json_decode((string) $row->rule, true) : null;
        try {
            return self::normalizePauseRule($stored);
        } catch (\InvalidArgumentException $e) {
            return ['enabled' => false, 'conditions' => []];
        }
    }

    public static function setPauseRule(array $rule, string $account = 'ebay1'): void
    {
        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => self::keyPause($account)],
            ['rule' => json_encode($rule), 'updated_at' => now()]
        );
    }

    /**
     * Does the pause rule fire for these numbers? AND binds tighter than OR. A condition with no value is ignored;
     * a rule with no usable condition (or switched off) never fires. Same logic as the modal in the browser.
     */
    public static function pauseMatches(?array $rule, float $inv, float $ovL30): bool
    {
        if (! $rule || empty($rule['enabled'])) {
            return false;
        }
        $vals = ['inv' => $inv, 'l30' => $ovL30, 'dil' => $inv > 0 ? ($ovL30 / $inv) * 100 : 0.0];
        $groups = [];
        $current = null;
        foreach ((array) ($rule['conditions'] ?? []) as $c) {
            $mode = $c['mode'] ?? 'number';
            $value = $c['value'] ?? '';
            if ($mode === 'column') {
                if (! isset($vals[$value])) {
                    continue;
                }
                $right = $vals[$value];
            } else {
                if ($value === '' || ! is_numeric($value)) {
                    continue;
                }
                $right = (float) $value;
            }
            $left = $vals[$c['column'] ?? ''] ?? null;
            if ($left === null) {
                continue;
            }
            $hit = match ($c['op'] ?? '') {
                '<' => $left < $right,
                '<=' => $left <= $right,
                '=' => abs($left - $right) < 1e-9,
                '!=' => abs($left - $right) >= 1e-9,
                '>=' => $left >= $right,
                '>' => $left > $right,
                default => false,
            };
            if ($current === null || ($c['join'] ?? 'AND') === 'OR') {
                $groups[] = [];
                $current = array_key_last($groups);
            }
            $groups[$current][] = $hit;
        }
        foreach ($groups as $g) {
            if (! in_array(false, $g, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  bool  $pauseMatch  the dynamic pause rule fired for this row (see pauseMatches)
     * @return array{rule:string,pause:bool,bid:?float,label:string,fallback:bool}
     *         fallback = no match for the category: the caller uses the old Dil vs SBid bid
     */
    public static function decide(float $inv, ?float $highest, ?float $manual = null, bool $pauseMatch = false): array
    {
        if ($inv <= 0) {
            return ['rule' => '1', 'pause' => true, 'bid' => null, 'label' => 'PMT rule 1: INV 0, ad paused', 'fallback' => false];
        }
        if ($pauseMatch) {
            return ['rule' => '1', 'pause' => true, 'bid' => null, 'label' => 'PMT rule 1: pause conditions matched, ad paused', 'fallback' => false];
        }
        if ($highest !== null) {
            return ['rule' => '2', 'pause' => false, 'bid' => max(0.0, round($highest, 2)), 'label' => 'PMT rule 2: category Highest', 'fallback' => false];
        }

        return ['rule' => '3', 'pause' => false, 'bid' => null, 'label' => 'PMT rule 3: no Highest for this category, old S Bid', 'fallback' => true];
    }
}
