<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Start an Ads Rule PMT push in the background (after the switch / ranges change, or after
 * inventory / metrics jobs). The command checks the switch itself and holds its own lock.
 */
final class PmtRuleAutoPush
{
    public const SPAWN_SECONDS = 600;

    /**
     * @param  'ebay1'|'ebay2'|'ebay3'|'all'  $account
     */
    public static function spawn(string $account = 'ebay1', bool $force = false): void
    {
        $account = strtolower(trim($account));
        if (! in_array($account, ['ebay1', 'ebay2', 'ebay3', 'all'], true)) {
            return;
        }
        if (! $force && ! Cache::add('ebay-pmt-rule-spawn-'.$account, 1, self::SPAWN_SECONDS)) {
            return;
        }

        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $cmd = "{$php} {$artisan} ebay:pmt-rule-auto-push ".escapeshellarg($account).' --mismatch';

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B '.$cmd, 'r'));

            return;
        }

        exec($cmd.' > /dev/null 2>&1 &');
    }

    /** Start a push for every listed account whose PMT switch is on. */
    public static function afterDataChange(string $account = 'all'): void
    {
        $accounts = $account === 'all' ? PmtCategoryBidRule::ACCOUNTS : [$account];
        foreach ($accounts as $acc) {
            if (in_array($acc, PmtCategoryBidRule::ACCOUNTS, true) && PmtCategoryBidRule::isOn($acc)) {
                self::spawn($acc);
            }
        }
    }
}
