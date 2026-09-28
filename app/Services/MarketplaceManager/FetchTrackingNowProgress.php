<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Cache;

class FetchTrackingNowProgress
{
    public const LATEST_KEY = 'mm.fetch_tracking.latest_run';

    public static function key(string $runId): string
    {
        return 'mm.fetch_tracking.progress.'.$runId;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function start(string $runId, array $extra = []): array
    {
        $payload = array_merge([
            'run_id' => $runId,
            'status' => 'queued',
            'percent' => 1,
            'message' => 'Queued. Waiting for the tracking worker…',
            'checked' => 0,
            'fulfilled' => 0,
            'skipped' => 0,
            'failed' => 0,
            'updated_at' => now()->toIso8601String(),
        ], $extra);

        Cache::put(self::key($runId), $payload, now()->addHours(2));
        Cache::put(self::LATEST_KEY, $runId, now()->addHours(2));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public static function update(string $runId, array $patch): array
    {
        $current = self::get($runId) ?? self::start($runId);
        $next = array_merge($current, $patch, [
            'run_id' => $runId,
            'updated_at' => now()->toIso8601String(),
        ]);
        $next['percent'] = max(0, min(100, (int) ($next['percent'] ?? 0)));
        Cache::put(self::key($runId), $next, now()->addHours(2));
        Cache::put(self::LATEST_KEY, $runId, now()->addHours(2));

        return $next;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(?string $runId = null): ?array
    {
        $runId = trim((string) ($runId ?: Cache::get(self::LATEST_KEY)));
        if ($runId === '') {
            return null;
        }
        $row = Cache::get(self::key($runId));

        return is_array($row) ? $row : null;
    }

    public static function isActive(?array $row): bool
    {
        if (! is_array($row)) {
            return false;
        }
        $status = (string) ($row['status'] ?? '');
        if (! in_array($status, ['queued', 'running'], true)) {
            return false;
        }
        $updated = strtotime((string) ($row['updated_at'] ?? '')) ?: 0;

        return $updated > 0 && $updated >= now()->subMinutes(45)->timestamp;
    }
}
