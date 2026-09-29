<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts amazon:ads-live-bid-bgt-sync when the grid has a new SBID/SBGT
 * that no longer matches the live value. The 21:50 run only sees numbers
 * saved at that minute; a later page load used to leave those rows pending
 * until the next night.
 */
final class AmazonAdsLiveSyncFollowUp
{
    public const DIRTY_KEY = 'amazon-ads-live-sync-dirty';

    public const RUN_KEY = 'amazon-ads-live-sync-run';

    public const SPAWN_KEY = 'amazon-ads-live-sync-spawn';

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function rowsNeedSync(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $bidOpen = ($row['bid_sync_color'] ?? '') !== AmazonAdsLiveSyncStatus::RED
                && AmazonAdsLiveSyncStatus::displayedBidsDiffer($row['last_sbid'] ?? null, $row['sbid'] ?? null);
            $bgtOpen = ($row['bgt_sync_color'] ?? '') !== AmazonAdsLiveSyncStatus::RED
                && AmazonAdsLiveSyncStatus::displayedBudgetsDiffer($row['bgt'] ?? null, $row['sbgt'] ?? null);
            if ($bidOpen || $bgtOpen) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function requestFromGrid(array $rows, string $table): void
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return;
        }
        if (! self::rowsNeedSync($rows)) {
            return;
        }
        self::request();
    }

    public static function request(): void
    {
        try {
            Cache::put(self::DIRTY_KEY, 1, now()->addHours(6));
            if (! Cache::add(self::SPAWN_KEY, 1, 90)) {
                return;
            }
            self::spawn();
        } catch (Throwable $e) {
            Log::warning('amazon-ads live sync follow-up skipped', ['error' => $e->getMessage()]);
        }
    }

    private static function spawn(): void
    {
        $php = PHP_BINARY ?: 'php';
        if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
            $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
            if ($cli !== '') {
                $php = $cli;
            }
        }
        $artisan = base_path('artisan');
        if ($php === '' || ! is_file($artisan)) {
            return;
        }
        $log = storage_path('logs/amazon-ads-live-sync.log');
        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan).' amazon:ads-live-bid-bgt-sync --limit=200 --no-interaction';
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            pclose(popen('start /B '.$cmd.' >> '.escapeshellarg($log).' 2>&1', 'r'));

            return;
        }
        pclose(popen('nohup '.$cmd.' >> '.escapeshellarg($log).' 2>&1 &', 'r'));
    }
}
