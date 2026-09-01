<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * AliExpress pricing: do not push SKUs whose SGROI is under the saved cutoff (default 30%).
 */
class AliexpressPushGuard
{
    public const MIN_SGROI = 30;

    public const CACHE_KEY = 'aliexpress_stop_push_sgroi_lt_40';

    public const CACHE_MIN_KEY = 'aliexpress_stop_push_sgroi_min';

    public static function filePath(): string
    {
        return storage_path('app/aliexpress-push-guard.json');
    }

    public static function minSgroi(): int
    {
        $cached = Cache::get(self::CACHE_MIN_KEY);
        if ($cached !== null) {
            return self::clampMin((int) $cached);
        }

        $n = self::clampMin((int) (self::read()['min_sgroi'] ?? self::MIN_SGROI));
        Cache::forever(self::CACHE_MIN_KEY, $n);

        return $n;
    }

    public static function stopLowSgroiEnabled(): bool
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $on = ! empty(self::read()['stop_sgroi_lt_40']);
        Cache::forever(self::CACHE_KEY, $on);

        return $on;
    }

    public static function setStopLowSgroi(bool $on, ?int $minSgroi = null): void
    {
        $data = self::read();
        $data['stop_sgroi_lt_40'] = $on;
        if ($minSgroi !== null) {
            $data['min_sgroi'] = self::clampMin($minSgroi);
        } elseif (! isset($data['min_sgroi'])) {
            $data['min_sgroi'] = self::MIN_SGROI;
        }
        $data['updated_at'] = now()->toDateTimeString();

        $dir = dirname(self::filePath());
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents(self::filePath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Cache::forever(self::CACHE_KEY, $on);
        Cache::forever(self::CACHE_MIN_KEY, (int) $data['min_sgroi']);
    }

    public static function shouldSkipSgroi(?float $sgroi): bool
    {
        return $sgroi !== null && $sgroi < self::minSgroi();
    }

    public static function sgroi(float $sprice, float $margin, float $lp, float $ship): int
    {
        if (! ($lp > 0) || ! ($sprice > 0)) {
            return 0;
        }

        return (int) round((($sprice * $margin - $lp - $ship) / $lp) * 100);
    }

    /**
     * @return array{stop_sgroi_lt_40?: bool, min_sgroi?: int, updated_at?: string}
     */
    private static function read(): array
    {
        $path = self::filePath();
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function clampMin(int $n): int
    {
        if ($n < 1) {
            return self::MIN_SGROI;
        }

        return min(300, $n);
    }
}
