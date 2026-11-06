<?php

namespace App\Http\Controllers\Campaigns;

use App\Console\Commands\EbayMultiBuyApplyCommand;
use App\Http\Controllers\Controller;
use App\Services\Ebay1VolumePricingService;
use App\Support\PmtCategoryBidRule;
use App\Support\PmtRuleAutoPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ads Rule PMT and Multi Buy for eBay 1, eBay 2 and eBay 3. The account comes from the route
 * default ('account' => 'ebay1' | 'ebay2' | 'ebay3'); every account has its own ranges, its own
 * On/Off switch and its own Multi Buy discounts.
 */
class EbayAccountRulesController extends Controller
{
    private const MULTI_BUY_DEFAULTS = ['buy2' => 1.0, 'buy3' => 2.0, 'buy4' => 3.0];

    private function account(Request $request): string
    {
        $account = (string) ($request->route()?->defaults['account'] ?? 'ebay1');

        return in_array($account, PmtCategoryBidRule::ACCOUNTS, true) ? $account : 'ebay1';
    }

    /**
     * Ads Rule PMT: highest / lowest suggested bid (%) per product category, as
     * {"Category": {"highest": 9.5, "lowest": 2}, ...}. (Older eBay 1 data stored a plain number = highest.)
     */
    public function getPmtCategoryBids(Request $request)
    {
        $account = $this->account($request);
        $row = DB::table('ebay_sbid_rules')->where('key', PmtCategoryBidRule::keyBids($account))->first();
        $stored = $row ? (json_decode($row->rule, true) ?: []) : [];

        $bids = [];
        foreach ($stored as $category => $value) {
            $bids[$category] = is_array($value)
                ? ['highest' => $value['highest'] ?? null, 'lowest' => $value['lowest'] ?? null]
                : ['highest' => $value, 'lowest' => null];
        }

        return response()->json([
            'bids' => (object) $bids,
            'enabled' => PmtCategoryBidRule::isOn($account),
        ]);
    }

    /** Ads Rule PMT On / Off switch. Turning it on starts a background push right away. */
    public function savePmtRuleSwitch(Request $request)
    {
        $account = $this->account($request);
        $on = $request->boolean('enabled');
        PmtCategoryBidRule::setEnabled($on, $account);
        if ($on) {
            PmtRuleAutoPush::spawn($account, true);
        }

        return response()->json(['success' => true, 'enabled' => $on]);
    }

    public function savePmtCategoryBids(Request $request)
    {
        $account = $this->account($request);
        $input = $request->input('bids', []);
        if (! is_array($input)) {
            return response()->json(['error' => 'Invalid data'], 422);
        }

        $bids = [];
        foreach ($input as $category => $pair) {
            $category = trim((string) $category);
            if ($category === '' || ! is_array($pair)) {
                continue;
            }

            $values = [];
            foreach (['highest', 'lowest'] as $field) {
                $v = $pair[$field] ?? null;
                if ($v === null || $v === '') {
                    $values[$field] = null;

                    continue;
                }
                if (! is_numeric($v) || $v < 0 || $v > 100) {
                    return response()->json(['error' => ucfirst($field).' for "'.$category.'" must be a number between 0 and 100'], 422);
                }
                $values[$field] = round((float) $v, 2);
            }

            if ($values['highest'] !== null && $values['lowest'] !== null && $values['lowest'] > $values['highest']) {
                return response()->json(['error' => 'Lowest cannot be greater than Highest for "'.$category.'"'], 422);
            }
            if ($values['highest'] === null && $values['lowest'] === null) {
                continue; // nothing set for this category
            }

            $bids[$category] = $values;
        }

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => PmtCategoryBidRule::keyBids($account)],
            ['rule' => json_encode($bids, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );

        // New ranges while the rule is on: push the changed bids in the background.
        $on = PmtCategoryBidRule::isOn($account);
        if ($on) {
            PmtRuleAutoPush::spawn($account, true);
        }

        return response()->json(['success' => true, 'bids' => (object) $bids, 'enabled' => $on]);
    }

    /** Multi Buy rule: discount % for "Buy 2 / Buy 3 / Buy 4", stored as {"buy2": 1, "buy3": 2, "buy4": 3}. */
    public function getMultiBuyRule(Request $request)
    {
        $account = $this->account($request);
        $row = DB::table('ebay_sbid_rules')->where('key', EbayMultiBuyApplyCommand::ruleKey($account))->first();
        $stored = $row ? (json_decode($row->rule, true) ?: []) : [];

        $out = [];
        foreach (self::MULTI_BUY_DEFAULTS as $field => $default) {
            $out[$field] = isset($stored[$field]) && is_numeric($stored[$field]) ? (float) $stored[$field] : $default;
        }

        return response()->json([
            'discounts' => $out,
            'defaults' => self::MULTI_BUY_DEFAULTS,
            'last_run' => Cache::get(EbayMultiBuyApplyCommand::resultKey($account)),
            'volume_pricing_auto' => $this->volumePricingAutoIsOn($account),
        ]);
    }

    public function saveMultiBuyRule(Request $request)
    {
        $account = $this->account($request);
        $input = $request->input('discounts', []);
        if (! is_array($input)) {
            return response()->json(['error' => 'Invalid data'], 422);
        }

        $out = [];
        foreach (array_keys(self::MULTI_BUY_DEFAULTS) as $field) {
            $v = $input[$field] ?? null;
            if ($v === null || $v === '' || ! is_numeric($v) || $v < 0 || $v > 100) {
                return response()->json(['error' => strtoupper(str_replace('buy', 'Buy ', $field)).' discount must be a number between 0 and 100'], 422);
            }
            $out[$field] = round((float) $v, 2);
        }

        DB::table('ebay_sbid_rules')->updateOrInsert(
            ['key' => EbayMultiBuyApplyCommand::ruleKey($account)],
            ['rule' => json_encode($out), 'updated_at' => now()]
        );

        $applying = false;
        if ($request->boolean('apply')) {
            $applying = $this->startMultiBuyApply($account);
        }

        return response()->json([
            'success' => true,
            'discounts' => $out,
            'applying' => $applying,
            'volume_pricing_auto' => $this->volumePricingAutoIsOn($account),
            'last_run' => Cache::get(EbayMultiBuyApplyCommand::resultKey($account)),
        ]);
    }

    /** True when the Volume Pricing page would re-push its own Buy 2/3/4 over the Multi Buy discounts (eBay 1 only). */
    private function volumePricingAutoIsOn(string $account): bool
    {
        if ($account !== 'ebay1') {
            return false;
        }
        try {
            $rules = app(Ebay1VolumePricingService::class)->loadRules();

            return ! empty($rules['enabled']) && ! empty($rules['auto_push']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Run ebay:multi-buy-apply detached, so it keeps going after this request ends. */
    private function startMultiBuyApply(string $account): bool
    {
        try {
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                return false;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $log = storage_path('logs/'.$account.'-multi-buy-apply.log');
            exec('nohup '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan'))
                .' ebay:multi-buy-apply '.escapeshellarg($account).' >> '.escapeshellarg($log).' 2>&1 &');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
