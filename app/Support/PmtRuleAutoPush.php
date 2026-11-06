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

    public static function spawn(bool $force = false): void
    {
        if (! $force && ! Cache::add('ebay-pmt-rule-spawn', 1, self::SPAWN_SECONDS)) {
            return;
        }

        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $cmd = "{$php} {$artisan} ebay:pmt-rule-auto-push --mismatch";

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B '.$cmd, 'r'));

            return;
        }

        exec($cmd.' > /dev/null 2>&1 &');
    }
}
