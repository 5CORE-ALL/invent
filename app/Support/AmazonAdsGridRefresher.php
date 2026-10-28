<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Starts amazon-ads:warm-grid in the background (never blocks the visitor who triggered it).
 * At most one start per minute; the command itself also holds a lock.
 */
final class AmazonAdsGridRefresher
{
    public static function kick(): void
    {
        try {
            if (! Cache::add('amz_ads_grid_kick', 1, 60)) {
                return;
            }
            $php = PHP_BINDIR.'/php';
            if (! is_executable($php)) {
                $php = 'php';
            }
            // The whole group is detached from exec()'s pipe; otherwise exec() waits for the job to finish.
            $cmd = '(cd '.escapeshellarg(base_path())
                .' && nohup nice -n 10 '.escapeshellarg($php).' -d memory_limit=2G'
                .' artisan amazon-ads:warm-grid >> '.escapeshellarg(storage_path('logs/amazon-ads-jobs.log'))
                .' 2>&1) > /dev/null 2>&1 &';
            exec($cmd);
        } catch (\Throwable) {
        }
    }
}
