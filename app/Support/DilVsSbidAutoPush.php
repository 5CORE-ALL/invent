<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Start a Dil vs SBid push after Dil, inventory, views, or CVR change.
 * One spawn per account per 10 minutes — the command still uses its own lock.
 */
final class DilVsSbidAutoPush
{
    public const SPAWN_SECONDS = 600;

    /**
     * @param  'ebay1'|'ebay2'|'ebay3'|'all'  $account
     */
    public static function spawnLockName(string $account): string
    {
        return 'ebay-dil-sbid-spawn-'.$account;
    }

    /**
     * @param  'ebay1'|'ebay2'|'ebay3'|'all'  $account
     */
    public static function afterDataChange(string $account = 'all'): void
    {
        $account = strtolower(trim($account));
        if (! in_array($account, ['ebay1', 'ebay2', 'ebay3', 'all'], true)) {
            return;
        }
        if (! Cache::add(self::spawnLockName($account), 1, self::SPAWN_SECONDS)) {
            return;
        }

        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $acc = escapeshellarg($account);
        $cmd = "{$php} {$artisan} ebay:dil-sbid-auto-push {$acc} --mismatch";

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B '.$cmd, 'r'));

            return;
        }

        exec($cmd.' > /dev/null 2>&1 &');
    }
}
