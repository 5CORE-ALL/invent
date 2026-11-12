<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * "Fresh" pull of ebay_2_metrics for /ebay2-tabulator-view.
 *
 * After S PRC pushes finish, the page asks for `app:fetch-ebay-two-metrics --fresh`: the command downloads the
 * live eBay 2 inventory report first, and only when that returned rows does it truncate ebay_2_metrics and
 * re-insert every listing with its new price. The command runs in a background process (the report takes
 * minutes); this class holds the shared status that the page polls.
 */
class Ebay2MetricsRefresh
{
    public const STATUS_KEY = 'ebay2:metrics_fresh_refresh_status';

    /** A "running" status older than this is treated as dead (crashed process) so the page is never stuck. */
    private const STALE_SECONDS = 2700;

    /**
     * @return array<string, mixed>|null
     */
    public static function status(): ?array
    {
        try {
            $status = Cache::get(self::STATUS_KEY);
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

    public static function isRunning(): bool
    {
        $status = self::status();

        return $status !== null && ! empty($status['running']);
    }

    public static function write(string $state, string $message, ?bool $running = null): void
    {
        $prev = self::status() ?? [];
        $running ??= $state === 'running';
        try {
            Cache::put(self::STATUS_KEY, [
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
            Log::warning('Ebay2MetricsRefresh status write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Start the background refresh. Returns [started, alreadyRunning, error].
     *
     * @return array{started: bool, already_running: bool, error: ?string}
     */
    public static function start(): array
    {
        if (self::isRunning()) {
            return ['started' => false, 'already_running' => true, 'error' => null];
        }

        self::write('running', 'Downloading live eBay 2 listings…');

        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/ebay2-metrics-fresh.log');

            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' app:fetch-ebay-two-metrics --fresh', 'r'));
            } else {
                $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                    .' app:fetch-ebay-two-metrics --fresh >> '.escapeshellarg($log).' 2>&1 &';
                pclose(popen($cmd, 'r'));
            }

            return ['started' => true, 'already_running' => false, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('eBay2 fresh metrics refresh spawn failed', ['error' => $e->getMessage()]);
            self::write('failed', 'Could not start refresh: '.$e->getMessage(), false);

            return ['started' => false, 'already_running' => false, 'error' => $e->getMessage()];
        }
    }
}
