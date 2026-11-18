<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Progress of the Ads Rule PMT push to eBay, one record per account, kept in the cache so the page
 * can poll it (GET .../campaign-ads/ebay-update-status) while the push runs in a background process.
 *
 * Record: {status: running|done|failed, phase, percent, detail, started_at, updated_at, finished_at, summary, message}
 */
final class PmtPushProgress
{
    /** A running record not touched for this long is treated as dead (the process was killed). */
    public const STALE_SECONDS = 600;

    private const KEEP_SECONDS = 3600;

    private static function key(string $account): string
    {
        return 'ebay-pmt-push-progress-'.$account;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $account): ?array
    {
        $r = Cache::get(self::key($account));
        if (! is_array($r)) {
            return null;
        }
        if (($r['status'] ?? '') === 'running' && (time() - (int) ($r['updated_ts'] ?? 0)) > self::STALE_SECONDS) {
            return null;
        }
        unset($r['updated_ts']);

        return $r;
    }

    public static function start(string $account, string $phase = 'Starting'): void
    {
        self::put($account, [
            'status' => 'running',
            'phase' => $phase,
            'percent' => 0,
            'detail' => '',
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'summary' => null,
            'message' => '',
        ]);
    }

    public static function update(string $account, string $phase, int $percent, string $detail = ''): void
    {
        $r = Cache::get(self::key($account));
        if (! is_array($r) || ($r['status'] ?? '') !== 'running') {
            $r = ['status' => 'running', 'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'summary' => null, 'message' => ''];
        }
        $r['phase'] = $phase;
        $r['percent'] = max((int) ($r['percent'] ?? 0), min(99, $percent)); // never goes backwards, 100 is only set by finish()
        $r['detail'] = $detail;
        self::put($account, $r);
    }

    /** @param  array<string, int>  $summary  success / unchanged / failed / skipped */
    public static function finish(string $account, array $summary, string $message = '', bool $ok = true): void
    {
        $r = Cache::get(self::key($account));
        $r = is_array($r) ? $r : ['started_at' => now()->toDateTimeString()];
        $r['status'] = $ok ? 'done' : 'failed';
        $r['phase'] = $ok ? 'Done' : 'Failed';
        $r['percent'] = 100;
        $r['detail'] = '';
        $r['summary'] = $summary;
        $r['message'] = $message;
        $r['finished_at'] = now()->toDateTimeString();
        self::put($account, $r);
    }

    /** @param  array<string, mixed>  $r */
    private static function put(string $account, array $r): void
    {
        $r['updated_ts'] = time();
        $r['updated_at'] = now()->toDateTimeString();
        Cache::put(self::key($account), $r, self::KEEP_SECONDS);
    }
}
