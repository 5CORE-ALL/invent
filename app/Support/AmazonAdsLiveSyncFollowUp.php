<?php

namespace App\Support;

use App\Services\AmazonAdsLiveBidBgtSyncService;
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

    public const DIRTY_ROWS_KEY = 'amazon-ads-live-sync-dirty-rows';

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
     * Campaigns on this page whose Lbid/Lbgt still differ from SBID/SBGT.
     * The cron's latest-day query misses an L30-only row and a SBID that was
     * just calculated for the grid, so the follow-up pushes these values directly.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function dirtySyncRows(array $rows, string $channel): array
    {
        $channel = $channel === 'sb' ? 'sb' : 'sp';
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            if ($cid === '') {
                continue;
            }
            $payload = [
                'campaign_id' => $cid,
                'channel' => $channel,
                'campaign_name' => trim((string) ($row['campaignName'] ?? $row['campaign_name'] ?? '')),
            ];
            $open = false;
            if (($row['bid_sync_color'] ?? '') !== AmazonAdsLiveSyncStatus::RED
                && AmazonAdsLiveSyncStatus::displayedBidsDiffer($row['last_sbid'] ?? null, $row['sbid'] ?? null)) {
                $bid = AmazonAdsLiveBidBgtSyncService::positiveNumber($row['sbid'] ?? null);
                if ($bid !== null) {
                    $payload['sbid'] = $bid;
                    $open = true;
                }
            }
            if (($row['bgt_sync_color'] ?? '') !== AmazonAdsLiveSyncStatus::RED
                && AmazonAdsLiveSyncStatus::displayedBudgetsDiffer($row['bgt'] ?? null, $row['sbgt'] ?? null)) {
                if (AmazonAdsSbgt::isExplicitZero($row['sbgt'] ?? null)) {
                    $payload['sbgt'] = 0.0;
                    $open = true;
                } else {
                    $parsed = AmazonAdsSbgt::parsePushableBudget($row['sbgt'] ?? null);
                    if ($parsed !== null) {
                        $payload['sbgt'] = (float) $parsed;
                        $open = true;
                    }
                }
            }
            $blankLive = AmazonAdsStoredLiveBid::isBlank($row['last_sbid'] ?? null);
            if ($blankLive && ! isset($payload['sbid']) && ($row['bid_sync_color'] ?? '') !== AmazonAdsLiveSyncStatus::RED) {
                $payload['pull_bid'] = true;
                $open = true;
            }
            if ($open) {
                $out[] = $payload;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function requestFromGrid(array $rows, string $table): void
    {
        if (! in_array($table, ['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'], true)) {
            return;
        }
        $channel = $table === 'amazon_sb_campaign_reports' ? 'sb' : 'sp';
        $dirty = self::dirtySyncRows($rows, $channel);
        if ($dirty === []) {
            return;
        }
        try {
            self::storeDirtyRows($dirty);
        } catch (Throwable $e) {
            Log::warning('amazon-ads live sync follow-up could not store dirty rows', ['error' => $e->getMessage()]);
        }
        self::request();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pullDirtyRows(): array
    {
        try {
            $rows = Cache::pull(self::DIRTY_ROWS_KEY, []);
        } catch (Throwable) {
            return [];
        }
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $dirty
     */
    public static function storeDirtyRows(array $dirty): void
    {
        $existing = Cache::get(self::DIRTY_ROWS_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }
        foreach ($dirty as $row) {
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            $channel = (string) ($row['channel'] ?? '');
            if ($cid === '' || ($channel !== 'sp' && $channel !== 'sb')) {
                continue;
            }
            $key = $channel."\0".$cid;
            $prev = is_array($existing[$key] ?? null) ? $existing[$key] : [];
            $existing[$key] = array_merge($prev, $row);
        }
        Cache::put(self::DIRTY_ROWS_KEY, $existing, now()->addHours(6));
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
