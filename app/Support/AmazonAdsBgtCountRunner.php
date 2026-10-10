<?php

namespace App\Support;

/**
 * Starts amazon-ads:bgt-counts without blocking the page.
 */
final class AmazonAdsBgtCountRunner
{
    public static function lockPath(): string
    {
        return storage_path('framework/amazon-ads-bgt-counts.lock');
    }

    public static function isRunning(): bool
    {
        $path = self::lockPath();
        if (! is_file($path)) {
            return false;
        }
        $fh = fopen($path, 'c');
        if ($fh === false) {
            return false;
        }
        $busy = ! flock($fh, LOCK_EX | LOCK_NB);
        if (! $busy) {
            flock($fh, LOCK_UN);
        }
        fclose($fh);

        return $busy;
    }

    public static function rerunPath(): string
    {
        return storage_path('framework/amazon-ads-bgt-counts.rerun');
    }

    public static function startInBackground(): void
    {
        if (self::isRunning()) {
            touch(self::rerunPath());

            return;
        }
        $php = PHP_BINDIR.'/php';
        if (! is_executable($php)) {
            $php = 'php';
        }
        $cmd = 'cd '.escapeshellarg(base_path())
            .' && nohup '.escapeshellarg($php)
            .' artisan amazon-ads:bgt-counts >> '.escapeshellarg(storage_path('logs/amazon-ads-bgt-counts.log'))
            .' 2>&1 &';
        exec($cmd);
    }
}
