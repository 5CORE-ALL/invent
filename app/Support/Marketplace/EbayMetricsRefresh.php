<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * "Fresh" pull of the eBay metrics table for /ebay-tabulator-view, /ebay2-tabulator-view and
 * /ebay3-tabulator-view.
 *
 * After S PRC pushes finish, the page asks for the channel's fetch command with `--fresh`: the command downloads
 * the live eBay inventory report first, and only when that returned rows does it truncate the metrics table and
 * re-insert every listing with its new price. The command runs in a background process (the report takes
 * minutes); this class holds the shared status that the page polls.
 */
class EbayMetricsRefresh
{
    /** @var array<string, array{command: string, table: string, label: string}> */
    public const CHANNELS = [
        'ebay1' => ['command' => 'app:fetch-ebay-reports', 'table' => 'ebay_metrics', 'label' => 'eBay 1'],
        'ebay2' => ['command' => 'app:fetch-ebay-two-metrics', 'table' => 'ebay_2_metrics', 'label' => 'eBay 2'],
        'ebay3' => ['command' => 'app:fetch-ebay-three-metrics', 'table' => 'ebay_3_metrics', 'label' => 'eBay 3'],
    ];

    /** A "running" status older than this is treated as dead (crashed process) so the page is never stuck. */
    private const STALE_SECONDS = 2700;

    public static function isSupported(string $channel): bool
    {
        return isset(self::CHANNELS[$channel]);
    }

    public static function label(string $channel): string
    {
        return self::CHANNELS[$channel]['label'] ?? $channel;
    }

    public static function statusKey(string $channel): string
    {
        return 'ebay:metrics_fresh_refresh_status:'.$channel;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function status(string $channel): ?array
    {
        try {
            $status = Cache::get(self::statusKey($channel));
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($status)) {
            return null;
        }
        if (! empty($status['running']) && (time() - (int) ($status['updated_at'] ?? 0)) > self::STALE_SECONDS) {
            $status['running'] = false;
            $status['state'] = 'failed';
            $status['message'] = 'Refresh timed out';
        }

        return $status;
    }

    public static function isRunning(string $channel): bool
    {
        $status = self::status($channel);

        return $status !== null && ! empty($status['running']);
    }

    public static function write(string $channel, string $state, string $message, ?bool $running = null): void
    {
        $prev = self::status($channel) ?? [];
        $running ??= $state === 'running';
        try {
            Cache::put(self::statusKey($channel), [
                'state' => $state,
                'running' => $running,
                'message' => $message,
                'started_at' => $state === 'running' && empty($prev['running'])
                    ? now()->toDateTimeString()
                    : ($prev['started_at'] ?? now()->toDateTimeString()),
                'finished_at' => $running ? null : now()->toDateTimeString(),
                'updated_at' => time(),
            ], 3600);
        } catch (\Throwable $e) {
            Log::warning('EbayMetricsRefresh status write failed', ['channel' => $channel, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Start the background refresh.
     *
     * @return array{started: bool, already_running: bool, error: ?string}
     */
    public static function start(string $channel): array
    {
        if (! self::isSupported($channel)) {
            return ['started' => false, 'already_running' => false, 'error' => 'Unsupported eBay channel'];
        }
        if (self::isRunning($channel)) {
            return ['started' => false, 'already_running' => true, 'error' => null];
        }

        $label = self::label($channel);
        self::write($channel, 'running', 'Downloading live '.$label.' listings…');

        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $command = self::CHANNELS[$channel]['command'].' --fresh';
            $log = storage_path('logs/'.$channel.'-metrics-fresh.log');

            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' '.$command, 'r'));
            } else {
                $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                    .' '.$command.' >> '.escapeshellarg($log).' 2>&1 &';
                pclose(popen($cmd, 'r'));
            }

            return ['started' => true, 'already_running' => false, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('eBay fresh metrics refresh spawn failed', ['channel' => $channel, 'error' => $e->getMessage()]);
            self::write($channel, 'failed', 'Could not start refresh: '.$e->getMessage(), false);

            return ['started' => false, 'already_running' => false, 'error' => $e->getMessage()];
        }
    }
}
